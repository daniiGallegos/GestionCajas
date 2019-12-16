<?php

namespace App\Http\Controllers;

use App\Service\CajaService;
use App\Service\CompraCajaService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use App\Http\Requests\CajaCreateRequest;
use App\Http\Requests\CajaUpdateRequest;
use DB;

class CajaController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */

    protected $caja;
    protected $compra;

    public function __construct(CajaService $caj,CompraCajaService $compra)
    {
        $this->caja = $caj;
        $this->compra = $compra;
    }


    public function index(Request $request)
    {
        // Get All Data
        $cajas = $this->caja->getCajas()
            ->NombreCaja($request->get('busquedaNombre'))
            ->PesoCaja($request->get('busquedaPeso'))
            ->paginate(10);

        return view('modulos.Caja.index', compact('cajas'));
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {

    }

    /**
     * Store a newly created resource in storage.
     *
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Http\Response
     */
    public function store(CajaCreateRequest $request)
    {
        if ($request->ajax()) {

            return $this->caja->save($request);
        }
    }

    /**
     * Display the specified resource.
     *
     * @param int $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {

    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param int $id
     * @return \Illuminate\Http\Response
     */
    public function edit($id)
    {
        return $this->caja->edit($id);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param \Illuminate\Http\Request $request
     * @param int $id
     * @return \Illuminate\Http\Response
     */
    public function update(CajaUpdateRequest $request, $id)
    {
        return $this->caja->update($request, $id);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param int $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        if ($id > 0) {
            return $this->caja->delete($id);

        }

    }

    public function reporteStock()
    {
        $cajas = $this->caja->getStockCajas()->paginate(10);

        return view('modulos.Reportes.Stock.stockPorCajas', compact('cajas'));
    }

    public function prestamosPorCaja($id)
    {
        $prestamos = $this->caja->getPrestamosPendientes($id)->paginate(10);

        $caja = $this->caja->getCaja($id);

        return view('modulos.Reportes.Stock.detallePrestamos', compact('prestamos', 'caja'));
    }

    public function comprasPorCaja($id)
    {
        $compras = $this->compra->getComprasPorCaja($id)->paginate(10);

        $caja = $this->caja->getCaja($id);

        return view('modulos.CompraCaja.showComprasPorCaja', compact('compras', 'caja'));
    }





}
