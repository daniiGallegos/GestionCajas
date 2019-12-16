<?php

namespace App\Http\Controllers;

use App\Service\PrestamoService;
use App\Service\ProductorService;
use App\Service\ReporteCajasDiaService;
use Carbon\Carbon;
use Illuminate\Http\Request;

class HomeController extends Controller
{
    protected $prestamo;
    protected $productor;
    protected $cajaDia;


    public function __construct(PrestamoService $in,ProductorService $prod,ReporteCajasDiaService $c)
    {
        $this->middleware('auth');
        $this->prestamo = $in;
        $this->productor = $prod;
        $this->cajaDia = $c;
    }

    public function index(){
        $totalPrestamo = $this->prestamo->totalPrestamosValle()->get();
        $totalEgreso = $this->prestamo->totalEgresosValle();
        $totalEntrega = $this->prestamo->totalEntregasValle()->get();
        $totalPrestamosProveedor = $this->productor->getNumeroProductoresDeudores()->get();
        $fecha = Carbon::now();
        $fecha = $fecha->format('Y-m-d');
        $totalCajasDia = $this->cajaDia->getCajasDiaTotal($fecha,$fecha);
        return view('modulos.inicio', compact('totalPrestamo','totalEntrega','totalPrestamosProveedor',
            'totalCajasDia','totalEgreso'));
    }
}
