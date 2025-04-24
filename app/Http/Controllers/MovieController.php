<?php

namespace App\Http\Controllers;

use App\Models\Movie;
use App\Models\Category;
use Illuminate\Support\Str;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Validator;

class MovieController extends Controller
{
    private $category;

    public function __construct(Category $category)
    {
        $this->category = $category;
    }

    public function index()
    {
        $movies = $this->getMoviesWithSearch(request('search'));
        return view('homepage', compact('movies'));
    }

    public function detail($id)
    {
        $movie = $this->findMovieById($id);
        return view('detail', compact('movie'));
    }

    public function create()
    {
        $categories = $this->category->all();
        return view('input', compact('categories'));
    }

    public function store(Request $request)
    {
        $validator = $this->validateMovie($request, true);
        if ($validator->fails()) {
            return $this->returnValidationErrors($validator);
        }

        $fileName = $this->handleCoverUpload($request);
        $this->createMovie($request, $fileName);

        return $this->redirectWithSuccess('/');
    }

    public function data()
    {
        $movies = Movie::latest()->paginate(10);
        return view('data-movies', compact('movies'));
    }

    public function form_edit($id)
    {
        $movie = $this->findMovieById($id);
        $categories = $this->category->all();
        return view('form-edit', compact('movie', 'categories'));
    }

    public function update(Request $request, $id)
    {
        $validator = $this->validateMovie($request);
        if ($validator->fails()) {
            return $this->returnValidationErrors($validator);
        }

        $movie = $this->findMovieById($id);
        $this->handleMovieUpdate($request, $movie);

        return $this->redirectWithSuccess('/movies/data');
    }

    public function delete($id)
    {
        $movie = $this->findMovieById($id);
        $this->deleteCover($movie->foto_sampul);
        $movie->delete();

        return $this->redirectWithSuccess('/movies/data');
    }

    // Helper functions

    private function validateMovie(Request $request, $isCreate = false)
    {
        $rules = [
            'judul' => 'required|string|max:255',
            'category_id' => 'required|integer',
            'sinopsis' => 'required|string',
            'tahun' => 'required|integer',
            'pemain' => 'required|string',
            'foto_sampul' => 'image|mimes:jpeg,png,jpg,gif,svg|max:2048',
        ];

        if ($isCreate) {
            $rules['id'] = ['required', 'string', 'max:255', Rule::unique('movies', 'id')];
            $rules['foto_sampul'] = 'required|' . $rules['foto_sampul'];
        }

        return Validator::make($request->all(), $rules);
    }

    private function getMoviesWithSearch($search)
    {
        return Movie::latest()
            ->when($search, fn($query) => $query->where('judul', 'like', '%' . $search . '%')
                ->orWhere('sinopsis', 'like', '%' . $search . '%'))
            ->paginate(6)
            ->withQueryString();
    }

    private function handleCoverUpload(Request $request)
    {
        return $this->generateUniqueFileName($request->file('foto_sampul'));
    }

    private function deleteCover($filename)
    {
        $path = public_path('images/' . $filename);
        if (File::exists($path)) {
            File::delete($path);
        }
    }

    private function movieData(Request $request, $fileName = null)
    {
        $data = $request->only(['id', 'judul', 'category_id', 'sinopsis', 'tahun', 'pemain']);
        if ($fileName) {
            $data['foto_sampul'] = $fileName;
        }
        return $data;
    }

    private function returnValidationErrors($validator)
    {
        return redirect()->back()->withErrors($validator)->withInput();
    }

    private function redirectWithSuccess($route)
    {
        return redirect($route)->with('success', 'Data berhasil disimpan');
    }

    private function handleMovieUpdate(Request $request, Movie $movie)
    {
        if ($request->hasFile('foto_sampul')) {
            $this->deleteCover($movie->foto_sampul);
            $fileName = $this->handleCoverUpload($request);
            $movie->update($this->movieData($request, $fileName));
        } else {
            $movie->update($this->movieData($request));
        }
    }

    private function createMovie(Request $request, $fileName)
    {
        Movie::create($this->movieData($request, $fileName));
    }

    private function generateUniqueFileName($file)
    {
        $fileName = Str::uuid()->toString() . '.' . $file->getClientOriginalExtension();
        $file->move(public_path('images'), $fileName);
        return $fileName;
    }

    private function findMovieById($id)
    {
        return Movie::findOrFail($id);
    }
}
