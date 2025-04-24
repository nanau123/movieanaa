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
    public function index()
    {
        $movies = Movie::latest()
            ->when(request('search'), function ($query) {
                $query->where('judul', 'like', '%' . request('search') . '%')
                    ->orWhere('sinopsis', 'like', '%' . request('search') . '%');
            })
            ->paginate(6)
            ->withQueryString();
        //
        return view('homepage', compact('movies'));
    }

    public function detail($id)
    {
        $movie = Movie::findOrFail($id);
        return view('detail', compact('movie'));
    }

    public function create()
    {
        $categories = Category::all();
        return view('input', compact('categories'));
    }

    public function store(Request $request)
    {
        $validator = $this->validateMovie($request, true);

        if ($validator->fails()) {
            return redirect()->back()->withErrors($validator)->withInput();
        }

        $fileName = $this->uploadCover($request);

        Movie::create($this->movieData($request, $fileName));

        return redirect('/')->with('success', 'Data berhasil disimpan');
    }

    public function data()
    {
        $movies = Movie::latest()->paginate(10);
        return view('data-movies', compact('movies'));
    }

    public function form_edit($id)
    {
        $movie = Movie::findOrFail($id);
        $categories = Category::all();
        return view('form-edit', compact('movie', 'categories'));
    }

    public function update(Request $request, $id)
    {
        $validator = $this->validateMovie($request);

        if ($validator->fails()) {
            return redirect()->back()->withErrors($validator)->withInput();
        }

        $movie = Movie::findOrFail($id);

        if ($request->hasFile('foto_sampul')) {
            $this->deleteCover($movie->foto_sampul);
            $fileName = $this->uploadCover($request);
            $movie->update($this->movieData($request, $fileName));
        } else {
            $movie->update($this->movieData($request));
        }

        return redirect('/movies/data')->with('success', 'Data berhasil diperbarui');
    }

    public function delete($id)
    {
        $movie = Movie::findOrFail($id);
        $this->deleteCover($movie->foto_sampul);
        $movie->delete();

        return redirect('/movies/data')->with('success', 'Data berhasil dihapus');
    }

    // ========== PRIVATE HELPERS ==========

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

    private function uploadCover(Request $request)
    {
        $randomName = Str::uuid()->toString();
        $extension = $request->file('foto_sampul')->getClientOriginalExtension();
        $fileName = $randomName . '.' . $extension;

        $request->file('foto_sampul')->move(public_path('images'), $fileName);

        return $fileName;
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
}