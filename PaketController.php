<?php

namespace App\Http\Controllers;

use App\Models\Rate;
use App\Models\Paket;
use App\Models\Images;
use App\Models\Destination;
use Illuminate\Support\Str;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;

class PaketController extends Controller
{
    public function index()
    {
        $tanggalSekarang = now()->format('Y-m-d');

        $paketList = Paket::with(['rates' => function ($query) use ($tanggalSekarang) {
            $query->where('date_from', '<=', $tanggalSekarang)
                ->where('date_end', '>=', $tanggalSekarang);
        }])->paginate(10);
        return view('admin/paket', compact('paketList'));
    }

    public function create()
    {
        return view('admin/create_paket');
    }


    public function store(Request $request)
    {
        // Validasi input
        $validatedData = $request->validate([
            'nama_paket' => 'required|string|max:255',
            'deskripsi_paket' => 'required|string',
            'detail_paket' => 'required|string',
            'destinasi' => 'required',
            'date_from.*' => 'required|date',
            'date_end.*' => 'required|date|after_or_equal:date_from.*',
            'harga.*' => 'required|numeric',
        ]);
        // Use a database transaction
        DB::beginTransaction();
        try {
            // Membuat instance Paket
            $destinasi = json_encode($request->input('destinasi'));
            $paket = Paket::create([
                'nama_paket' => $request->input('nama_paket'),
                'slug' => Str::slug($request->input('nama_paket')),
                'deskripsi_paket' => $request->input('deskripsi_paket'),
                'detail_paket' => $request->input('detail_paket'),
                'destinasi' => $destinasi,
            ]);
            // Membuat Rates
            foreach ($request->date_from as $index => $dateFrom) {
                $dateEnd = $request->date_end[$index];
                $harga = $request->harga[$index];

                // Simpan data ke database
                Rate::create([
                    'paket_id' => $paket->id,
                    'date_from' => $dateFrom,
                    'date_end' => $dateEnd,
                    'harga' => $harga,
                ]);
            }
            // Menyimpan data gambarr Paket
            if ($request->hasFile("gambar_paket")) {
                $files = $request->file("gambar_paket");
                foreach ($files as $file) {
                    $nama_gambar = time() . '_' . $file->getClientOriginalName();
                    $file->storeAs('pakets', $nama_gambar);
                    Images::create([
                        'image' => $nama_gambar,
                        'paket_id' => $paket->id,
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
        $paket = Paket::findOrFail($id);
        $destination = Destination::all();
        $selected = json_decode($paket->destinasi);
        $rates = Rate::where('paket_id', $id)->get();
        $rateCount = 3;
        return view('admin/edit_paket', compact('paket', 'destination', 'selected', 'rates', 'rateCount'));
    }



    public function update(Request $request, $id)
    {
        // dd($request);
        // Validasi input
        $validatedData = $request->validate([
            'nama_paket' => 'required|string|max:255',
            'deskripsi_paket' => 'required|string',
            'detail_paket' => 'required|string',
            'destinasi' => 'required',
            'date_from.*' => 'required|date',
            'date_end.*' => 'required|date|after_or_equal:date_from.*',
            'harga.*' => 'required|numeric',
        ]);

        // Use a database transaction
        DB::beginTransaction();

        try {
            // Mencari paket berdasarkan ID
            $paket = Paket::find($id);
            // Menyimpan data gambarr Paket
            if ($request->hasFile("gambar_paket")) {
                $files = $request->file("gambar_paket");
                foreach ($files as $file) {
                    $nama_gambar = time() . '_' . $file->getClientOriginalName();
                    $file->storeAs('pakets', $nama_gambar);
                    $image = Images::create([
                        'image' => $nama_gambar,
                        'paket_id' => $paket->id,
                    ]);
                }
            }

            Rate::where('paket_id', $paket->id)->delete();
            // Membuat Rates
            foreach ($request->date_from as $index => $dateFrom) {
                $dateEnd = $request->date_end[$index];
                $harga = $request->harga[$index];

                // Simpan data ke database
                Rate::create([
                    'paket_id' => $paket->id,
                    'date_from' => $dateFrom,
                    'date_end' => $dateEnd,
                    'harga' => $harga,
                ]);
            }

            // Mengupdate data paket
            $paket->nama_paket = $request->input('nama_paket');
            $paket->deskripsi_paket = $request->input('deskripsi_paket');
            $paket->slug = Str::slug($request->input('nama_paket'));
            $paket->detail_paket = $request->input('detail_paket');
            $paket->destinasi = json_encode($request->input('destinasi'));

            // Menyimpan perubahan ke database
            $paket->save();



            // Commit the transaction if everything is successful
            DB::commit();

            // Redirect or respond with success message
            return redirect()->route('paket.index')->with(['success' => 'Data Paket berhasil diupdate']);
        } catch (\Exception $e) {
            // An error occurred, rollback the transaction
            DB::rollback();
            dd('error', $e);
            // Handle the error, you can log it or respond with an error message
            return response()->json(['success' => false, 'message' => 'Error updating data']);
        }
    }


    public function destroy($id)
    {
        // Use a database transaction
        DB::beginTransaction();

        try {
            $paket = Paket::find($id);
            $paket->delete();

            // Commit the transaction if everything is successful
            DB::commit();

            return response()->json(['success' => 'Data paket berhasil dihapus']);
        } catch (\Exception $e) {
            // An error occurred, rollback the transaction
            DB::rollback();

            // Handle the error, you can log it or respond with an error message
            return response()->json(['success' => false, 'message' => 'Error deleting data']);
        }
    }
}
