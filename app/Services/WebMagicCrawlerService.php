<?php

declare(strict_types=1);

namespace App\Services;

use App\Clients\WebMagicClient;
use App\Models\Article;
use Exception;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use Symfony\Component\DomCrawler\Crawler;

class WebMagicCrawlerService
{
    protected PendingRequest $client;
    protected string $tokenUrl;
    protected string $articlesUrlBase;
    protected int $categoryId;
    protected ?string $token = null;
    protected string $logChannel = 'web-magic'; // Канал логування

    public function __construct()
    {
        $this->client = WebMagicClient::get();

        $this->tokenUrl = config('web-scraping.web-magic.token-url');
        $this->articlesUrlBase = config('web-scraping.web-magic.filtered-articles-url');
        $this->categoryId = (int)config('web-scraping.web-magic.articles-category-id');
    }

    /**
     * Запускає процес скрейпінгу та повертає відфільтровані статті.
     *
     * @return array<int, array{link: string, title: string, published_at: string|null}>
     */
    public function run(): array
    {
        $this->token = $this->resolveToken();

        if (!$this->token) {
            Log::driver($this->logChannel)->error("Could not retrieve token. Stopping crawl.");
            return [];
        }

        $allFilteredArticles = [];
        $cutoffDate = Carbon::now()->subMonths(4);
        $page = 1;
        $keepFetchingPages = true; // Прапорець для продовження завантаження сторінок

        Log::driver($this->logChannel)->info(
            "Starting crawl. Category ID: {$this->categoryId}. Cutoff date: {$cutoffDate->toDateString()}"
        );

        while ($keepFetchingPages) {
            Log::driver($this->logChannel)->info("Fetching page {$page}...");

            $htmlContent = $this->fetchArticlesHtmlForPage($page);

            if ($htmlContent === null) {
                Log::driver($this->logChannel)->error("Failed to fetch HTML for page {$page}. Stopping.");
                break; // Зупиняємось, якщо не вдалося завантажити сторінку
            }

            $crawler = new Crawler($htmlContent);

            // Парсимо статті на поточній сторінці
            $pageResult = $this->parseArticlesFromCrawler($crawler, $cutoffDate);

            // Додаємо відфільтровані статті цієї сторінки до загального списку
            if (!empty($pageResult['articles'])) {
                $allFilteredArticles = array_merge($allFilteredArticles, $pageResult['articles']);
            }

            // Перевіряємо, чи потрібно продовжувати
            // 1. Чи знайшли ми статтю, старшу за дату відсікання?
            // 2. Чи є посилання "Next" на поточній сторінці?
            $hasNextPageLink = $crawler->filter(
                    'ul.pagination li.__next a.js_btn-next-pagination:not(.disabled)'
                )->count() > 0;

            if (!$pageResult['shouldContinueSearching'] || !$hasNextPageLink) {
                $keepFetchingPages = false;
                if (!$pageResult['shouldContinueSearching']) {
                    Log::driver($this->logChannel)->info(
                        "Stopping search: found article older than cutoff date on page {$page}."
                    );
                }
                if (!$hasNextPageLink) {
                    Log::driver($this->logChannel)->info("Stopping search: no 'Next' page link found on page {$page}.");
                }
            }

            if ($keepFetchingPages) {
                $page++;
            }
        }

        Log::driver($this->logChannel)->info("Crawl finished. Total articles found: " . count($allFilteredArticles));

        DB::table((new Article)->getTable())->delete();
        DB::table((new Article)->getTable())->insert($allFilteredArticles);

        return $allFilteredArticles;
    }

    /**
     * Отримує CSRF-токен зі сторінки.
     */
    protected function resolveToken(): ?string
    {
        if ($this->token) {
            return $this->token;
        }

        Log::driver($this->logChannel)->info("Attempting to resolve token from: {$this->tokenUrl}");

        try {
            $response = $this->client->get($this->tokenUrl);

            if ($response->successful()) {
                $html = $response->body();
                $mainCrawler = new Crawler($html);

                $tokenNode = $mainCrawler->filter('input[name="_token"]');
                if ($tokenNode->count() > 0) {
                    $this->token = $tokenNode->attr('value');

                    return $this->token;
                }

                Log::driver($this->logChannel)->warning("Token field not found on page: {$this->tokenUrl}");

                return null;
            }

            Log::driver($this->logChannel)->error(
                "Failed to fetch token page. Status: " . $response->status() . " URL: " . $this->tokenUrl
            );
        } catch (ConnectionException $e) {
            Log::driver($this->logChannel)->error("Connection error fetching token: " . $e->getMessage());
        } catch (Exception $e) {
            Log::driver($this->logChannel)->error("Error resolving token: " . $e->getMessage());
        }

        return null;
    }

    /**
     * Завантажує HTML-контент для вказаної сторінки статей.
     */
    protected function fetchArticlesHtmlForPage(int $pageNumber): ?string
    {
        $postData = [
            '_token' => $this->token,
            'page' => $pageNumber,
            'category_filter' => $this->categoryId,
        ];

        try {
            $response = $this->client
                ->asForm() // Надсилаємо дані як application/x-www-form-urlencoded
                ->withHeaders([
                    'X-Requested-With' => 'XMLHttpRequest',
                    'Referer' => $this->tokenUrl,
                ])
                ->post($this->articlesUrlBase, $postData);

            if ($response->successful()) {
                return $response->body();
            }

            Log::driver($this->logChannel)->error(
                "Failed to fetch articles page {$pageNumber}. Status: {$response->status()}. URL: {$this->articlesUrlBase}. Response: " . substr(
                    $response->body() ?? '',
                    0,
                    500
                )
            );

            if ($response->status() === 419) {
                Log::driver($this->logChannel)->error(
                    "Received 419 status (CSRF Token Mismatch / Page Expired). Token might be invalid."
                );
            }
        } catch (ConnectionException $e) {
            Log::driver($this->logChannel)->error(
                "Connection error fetching articles page {$pageNumber}: " . $e->getMessage()
            );
        } catch (Exception $e) {
            Log::driver($this->logChannel)->error(
                "Generic error fetching articles page {$pageNumber}: " . $e->getMessage()
            );
        }

        return null;
    }

    /**
     * Парсить статті з об'єкта Crawler та фільтрує їх за датою.
     *
     * @param Crawler $crawler Об'єкт Crawler з HTML поточної сторінки
     * @param Carbon $cutoffDate Дата відсікання
     * @return array{articles: array, shouldContinueSearching: bool}
     * 'articles': Масив знайдених та відфільтрованих статей на цій сторінці.
     * 'shouldContinueSearching': true, якщо всі статті на сторінці новіші за дату відсікання.
     */
    protected function parseArticlesFromCrawler(Crawler $crawler, Carbon $cutoffDate): array
    {
        $articlesOnPage = [];
        $shouldContinueSearching = true;

        $crawler->filter('a.articles-row')->each(
            function (Crawler $node) use (&$articlesOnPage, $cutoffDate, &$shouldContinueSearching) {
                // Не обробляємо далі, якщо вже знайшли стару статтю на цій сторінці
                if (!$shouldContinueSearching) {
                    return;
                }

                $articleData = [];
                $articleDate = null;

                try {
                    $articleData['link'] = $node->attr('href');

                    $titleNode = $node->filter('.articles-r p.articles-ttl');
                    $articleData['title'] = $titleNode->count() > 0 ? trim($titleNode->text()) : 'N/A';
                    if ($articleData['title'] === 'N/A') {
                        Log::driver($this->logChannel)->warning(
                            "Could not find title for article link: " . $articleData['link']
                        );
                    }

                    $dateNode = $node->filter('.articles-r .articles-r__top .articles-date');
                    if ($dateNode->count() > 0) {
                        $dateString = trim($dateNode->text());
                        try {
                            $articleDate = Carbon::createFromFormat('M d, Y', $dateString);
                            $articleData['published_at'] = $articleDate->toDateString(); // Зберігаємо як Y-m-d
                        } catch (InvalidArgumentException $e) {
                            Log::driver($this->logChannel)->error(
                                "Could not parse date string '{$dateString}' for article: " . $articleData['link'] . ". Error: " . $e->getMessage(
                                )
                            );
                            $articleData['published_at'] = null;
                        }
                    } else {
                        Log::driver($this->logChannel)->warning(
                            "Could not find date for article link: " . $articleData['link']
                        );
                        $articleData['published_at'] = null;
                    }

                    if ($articleDate && $articleDate->greaterThanOrEqualTo($cutoffDate)) {
                        // Стаття підходить, додаємо її
                        $articlesOnPage[] = $articleData;
                    } elseif ($articleDate) {
                        // Стаття занадто стара, встановлюємо прапорець для зупинки
                        $shouldContinueSearching = false;
                    }
                } catch (Exception $e) {
                    $linkForError = $articleData['link'] ?? "unknown";
                    Log::driver($this->logChannel)->error(
                        "Error processing article node (link: $linkForError): " . $e->getMessage()
                    );
                    $shouldContinueSearching = false;
                }
            }
        );

        return [
            'articles' => $articlesOnPage,
            'shouldContinueSearching' => $shouldContinueSearching,
        ];
    }
}
