<?php

namespace App\Http\Controllers;

use App\Models\Article;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ArticleController extends Controller
{
    public function index(Request $request): View
    {
        $sortableColumns = ['title', 'published_at'];
        $sortColumn = $request->query('sort', 'title');
        $sortDirection = $request->query('direction', 'asc');

        if (!in_array($sortColumn, $sortableColumns, true)) {
            $sortColumn = 'title';
        }
        if (!in_array(strtolower($sortDirection), ['asc', 'desc'])) {
            $sortDirection = 'asc';
        }

        $articles = Article::query()
            ->orderBy($sortColumn, $sortDirection)
            ->paginate(15);

        return view('articles.index', [
            'articles' => $articles,
            'sortColumn' => $sortColumn,
            'sortDirection' => $sortDirection,
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'title' => ['required'],
            'link' => ['required'],
            'published_at' => ['required', 'date'],
        ]);

        return Article::create($data);
    }

    public function show(Article $article)
    {
        return $article;
    }

    public function update(Request $request, Article $article)
    {
        $data = $request->validate([
            'title' => ['required'],
            'link' => ['required'],
            'published_at' => ['required', 'date'],
        ]);

        $article->update($data);

        return $article;
    }

    public function destroy(Article $article)
    {
        $article->delete();

        return response()->json();
    }
}
