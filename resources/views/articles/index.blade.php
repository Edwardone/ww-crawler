<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Scraped Articles</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        // Базова конфігурація Tailwind (необов'язково, але корисно)
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: {
                        // Використання системного шрифту sans-serif за замовчуванням
                        sans: ['Inter', 'ui-sans-serif', 'system-ui', '-apple-system', 'BlinkMacSystemFont', '"Segoe UI"', 'Roboto', '"Helvetica Neue"', 'Arial', '"Noto Sans"', 'sans-serif', '"Apple Color Emoji"', '"Segoe UI Emoji"', '"Segoe UI Symbol"', '"Noto Color Emoji"'],
                    },
                }
            }
        }
    </script>
    <style type="text/tailwindcss">
        /* Додаткові стилі або налаштування Tailwind можна додати тут */
        /* Стилі для індикаторів сортування */
        .sort-asc::after {
            content: ' ▲';
            display: inline-block; /* Щоб ::after був поруч з текстом */
            margin-left: 0.25rem; /* Невеликий відступ */
        }
        .sort-desc::after {
            content: ' ▼';
            display: inline-block;
            margin-left: 0.25rem;
        }
        /* Стилі для пагінації Laravel за замовчуванням */
        .pagination {
            @apply flex justify-center space-x-1 mt-6; /* Центрування, відступи, верхній відступ */
        }
        .pagination li a,
        .pagination li span {
            @apply px-3 py-2 leading-tight text-gray-500 bg-white border border-gray-300 hover:bg-gray-100 hover:text-gray-700 rounded; /* Стилі для посилань/спанів */
        }
        .pagination li.active span {
            @apply z-10 px-3 py-2 leading-tight text-blue-600 border border-blue-300 bg-blue-50 hover:bg-blue-100 hover:text-blue-700 rounded; /* Стилі для активної сторінки */
        }
        .pagination li.disabled span {
            @apply px-3 py-2 leading-tight text-gray-400 bg-white border border-gray-300 cursor-not-allowed rounded; /* Стилі для неактивних елементів */
        }
    </style>
</head>
<body class="bg-gray-100 font-sans antialiased text-gray-800">

<div class="container mx-auto px-4 py-8"> <h1 class="text-3xl font-bold mb-6 text-center text-gray-700">Scraped Articles</h1> <div class="bg-white shadow-md rounded-lg overflow-hidden"> <table class="min-w-full leading-normal"> <thead>
            <tr>
                <th class="px-5 py-3 border-b-2 border-gray-200 bg-gray-100 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider cursor-pointer hover:bg-gray-200">
                    {{-- Посилання для сортування за Title --}}
                    @php
                        $titleDirection = ($sortColumn == 'title' && $sortDirection == 'asc') ? 'desc' : 'asc';
                        $titleSortClass = ($sortColumn == 'title') ? 'sort-' . $sortDirection : '';
                    @endphp
                    <a href="{{ route('articles.index', ['sort' => 'title', 'direction' => $titleDirection]) }}" class="block {{ $titleSortClass }}">
                        Title
                    </a>
                </th>
                <th class="px-5 py-3 border-b-2 border-gray-200 bg-gray-100 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider cursor-pointer hover:bg-gray-200">
                    {{-- Посилання для сортування за Published Date --}}
                    @php
                        $dateDirection = ($sortColumn == 'published_at' && $sortDirection == 'asc') ? 'desc' : 'asc';
                        $dateSortClass = ($sortColumn == 'published_at') ? 'sort-' . $sortDirection : '';
                    @endphp
                    <a href="{{ route('articles.index', ['sort' => 'published_at', 'direction' => $dateDirection]) }}" class="block {{ $dateSortClass }}">
                        Published Date
                    </a>
                </th>
            </tr>
            </thead>
            <tbody>
            @forelse ($articles as $article)
                <tr class="hover:bg-gray-50"> <td class="px-5 py-4 border-b border-gray-200 bg-white text-sm">
                        <a href="{{ $article->link }}" target="_blank" rel="noopener noreferrer" class="text-blue-600 hover:text-blue-800 hover:underline">
                            {{ $article->title }}
                        </a>
                    </td>
                    <td class="px-5 py-4 border-b border-gray-200 bg-white text-sm">
                        {{ $article->published_at?->format('d.m.Y') ?? 'N/A' }}
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="2" class="px-5 py-5 border-b border-gray-200 bg-white text-sm text-center text-gray-500">
                        No articles found. Please run the update command.(php artisan parse:blog)
                    </td>
                </tr>
            @endforelse
            </tbody>
        </table>
    </div>

    {{-- Посилання пагінації (стилі застосовуються через @apply вище) --}}
    <div class="mt-6">
        {{ $articles->appends(request()->query())->links() }}
    </div>

</div> </body>
</html>
