<?php

namespace App\Http\Controllers;

use App\Models\Images;
use App\Models\Pemetaan;
use App\Models\Destination;
use Illuminate\Support\Str;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class DestinationController extends Controller
{
    public function index()
    {
        $destinationList = Destination::paginate(10)->withQueryString();
        return view('admin/destination', compact('destinationList'));
    }

    public function create()
    {
        return view('admin/create_destination');
    }


    public function store(Request $request)
    {
        // Validasi input
        $validatedData = $request->validate([
            'nama_destinasi' => 'required|string|max:255',
            'jenis_destinasi' => 'required|string|max:255',
            'no_telp' => 'required|string|max:15',
            'alamat' => 'required|string|max:255',
            'cover_destinasi' => 'required|image|mimes:jpeg,png,jpg,gif,svg|max:2048',
            'deskripsi_destinasi' => 'required|string',
        ]);

        // Use a database transaction
        DB::beginTransaction();

        try {
            // Upload gambar
            if ($request->hasFile('cover_destinasi')) {
                $gambar = $request->file('cover_destinasi');
                $nama_gambar = time() . '_' . $gambar->getClientOriginalName();
                $gambar->storeAs('destinations', $nama_gambar);
            }
            // Mendapatkan user_id dari pengguna yang saat ini login
            $user_id = Auth::id();

            // Menyimpan data destinasi ke database
            $destination = Destination::create([
                'nama_destinasi' => $request->input('nama_destinasi'),
                'slug' => Str::slug($request->input('nama_destinasi')),
                'jenis_destinasi' => $request->input('jenis_destinasi'),
                'no_telp' => $request->input('no_telp'),
                'alamat' => $request->input('alamat'),
                'gambar_destinasi' => $nama_gambar,
                'deskripsi_destinasi' => $request->input('deskripsi_destinasi'),
                'user_id' => $user_id,
            ]);

            // Membuat Pemetaan
            Pemetaan::create([
                'destinasi_id' => $destination->id,
            ]);

            // Menyimpan data gambarr destinasi
            if ($request->hasFile("gambar_destinasi")) {
                $files = $request->file("gambar_destinasi");
                foreach ($files as $file) {
                    $nama_gambar = time() . '_' . $file->getClientOriginalName();
                    $file->storeAs('destinations', $nama_gambar);
                    Images::create([
                        'image' => $nama_gambar,
                        'destination_id' => $destination->id,
                    ]);
                }
            }


            // Commit the transaction if everything is successful
            DB::commit();

            // Redirect or respond with success message
            return response()->json(['success' => true, 'message' => 'Data added successfully']);
        } catch (\Exception $e) {
            // An error occurred, rollback the transaction
            DB::rollback();
            dd($e);
            // Handle the error, you can log it or respond with an error message
            return response()->json(['success' => false, 'message' => 'Error adding data']);
        }
    }

    public function edit($id)
    {
        $destination = Destination::findOrFail($id);
        return view('admin/edit_destination', compact('destination'));
    }



    public function update(Request $request, $id)
    {

        // Validasi input
        $validatedData = $request->validate([
            'nama_destinasi' => 'required|string|max:255',
            'jenis_destinasi' => 'required|string|max:255',
            'no_telp' => 'required|string|max:15',
            'alamat' => 'required|string|max:255',
            'cover_destinasi' => 'image|mimes:jpeg,png,jpg,gif,svg|max:2048',
            'deskripsi_destinasi' => 'required|string',
        ]);

        // Find the destination
        $destination = Destination::findOrFail($id);

        // Use a database transaction
        DB::beginTransaction();

        try {
            // Upload gambar if provided
            if ($request->hasFile('cover_destinasi')) {
                $gambar = $request->file('cover_destinasi');
                $nama_gambar = time() . '_' . $gambar->getClientOriginalName();
                $gambar->storeAs('destinations', $nama_gambar);

                // Delete the old image if it exists
                $this->deleteImage($destination->gambar_destinasi);

                // Update destination data
                $destination->update([
                    'slug' => Str::slug($request->input('nama_destinasi')),
                    'nama_destinasi' => $request->input('nama_destinasi'),
                    'jenis_destinasi' => $request->input('jenis_destinasi'),
                    'no_telp' => $request->input('no_telp'),
                    'alamat' => $request->input('alamat'),
                    'gambar_destinasi' => $nama_gambar,
                    'deskripsi_destinasi' => $request->input('deskripsi_destinasi'),
                ]);
            } else {
                // Update destination data without changing the image
                $destination->update([
                    'slug' => Str::slug($request->input('nama_destinasi')),
                    'nama_destinasi' => $request->input('nama_destinasi'),
                    'jenis_destinasi' => $request->input('jenis_destinasi'),
                    'no_telp' => $request->input('no_telp'),
                    'alamat' => $request->input('alamat'),
                    'deskripsi_destinasi' => $request->input('deskripsi_destinasi'),
                ]);
            }

            // Menyimpan data gambarr destinasi
            if ($request->hasFile("gambar_destinasi")) {
                $files = $request->file("gambar_destinasi");
                foreach ($files as $file) {
                    $nama_gambar = time() . '_' . $file->getClientOriginalName();
                    $file->storeAs('destinations', $nama_gambar);
                    Images::create([
                        'image' => $nama_gambar,
                        'destination_id' => $destination->id,
                    ]);
                }
            }


            // Commit the transaction if everything is successful
            DB::commit();

            // SweetAlert success notification
            return redirect()->route('destinasi.index')->with(['success' => 'Data Destinasi berhasil diupdate']);
        } catch (\Exception $e) {
            // An error occurred, rollback the transaction
            DB::rollback();

            // SweetAlert error notification
            return back()->with(['success' => false, 'message' => 'Error update data']);
        }
    }


    // Helper method to delete image
    private function deleteImage($gambar_destinasi)
    {
        if ($gambar_destinasi) {
            $gambar_destinasi = public_path('destinations/' . $gambar_destinasi);

            // Hapus gambar hanya jika file tersebut ada
            if (file_exists($gambar_destinasi)) {
                unlink($gambar_destinasi);
            }
        }
    }


    public function destroy($id)
    {
        // Use a database transaction
        DB::beginTransaction();

        try {
            $destination = Destination::findOrFail($id);

            // Hapus terlebih dahulu data terkait di tabel pemetaans
            $destination->pemetaan()->delete();

            // Hapus file terkait jika ada
            if (Storage::disk('public')->exists($destination->gambar_destinasi)) {
                Storage::disk('public')->delete($destination->gambar_destinasi);
            }

            // Sekarang, Anda bisa menghapus destinasi
            $destination->delete();

            // Commit the transaction if everything is successful
            DB::commit();

            return response()->json(['success' => 'Data Destinasi berhasil dihapus']);
        } catch (\Exception $e) {
            dd($e);
            // An error occurred, rollback the transaction
            DB::rollback();
            // Handle the error, you can log it or respond with an error message
            return response()->json(['success' => false, 'message' => 'Error deleting data']);
        }
    }

    public function destroyImage($id)
    {
        // Use a database transaction
        DB::beginTransaction();
        try {
            $image = Images::findOrFail($id);

            // Hapus file terkait jika ada
            if (Storage::disk('public')->exists($image->image)) {
                Storage::disk('public')->delete($image->image);
            }

            // Sekarang, Anda bisa menghapus destinasi
            $image->delete();
            // Commit the transaction if everything is successful
            DB::commit();

            return response()->json(['success' => 'Gambar berhasil dihapus']);
        } catch (\Exception $e) {
            // An error occurred, rollback the transaction
            DB::rollback();
            // Handle the error, you can log it or respond with an error message
            return response()->json(['success' => false, 'message' => 'Error deleting data']);
        }
    }



    public function getDestinationCounts()
    {
        try {
            // Retrieve destination counts from the database
            $destinationCounts = Destination::select('jenis_destinasi', \Illuminate\Support\Facades\DB::raw('count(*) as count'))
                ->groupBy('jenis_destinasi')
                ->pluck('count', 'jenis_destinasi');

            return response()->json($destinationCounts);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }
}
