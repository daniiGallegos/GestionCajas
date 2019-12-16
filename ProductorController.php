<?php

namespace App\Http\Controllers;


use Illuminate\Http\Request;
use App\Http\Requests\ProductorCreateRequest;
use App\Http\Requests\ProductorUpdateRequest;
use App\Service\ProductorService;

class ProductorController extends Controller
{

    protected $productor;

    public function __construct(ProductorService $prod)
    {
        $this->productor = $prod;
    }

    public function index(Request $request)
    {
        $productores = $this->productor->getProductores()
            ->RutProductor($request->get('busquedaRut'))
            ->ApellidoProductor($request->get('busquedaApellido'))
            ->CodigoProductor($request->get('busquedaCodigo'))
            ->paginate(10);
        return view('modulos.Productor.index', compact('productores'));
    }


    public function mostrarDetalleContacto($id)
    {
        $contacto= $this->productor->getDetalleContacto($id)
            ->paginate(10);
        return view('modulos.Productor.detalleContacto', compact('contacto'));
    }

    /**
     * Guardar una nueva tupla
     */
    public function store(ProductorCreateRequest $request)
    {
        if ($request->ajax()) {
            return $this->productor->save($request);
        }
    }


    public function show($id)
    {
        //
    }

    public function edit($id)
    {
        if ($id > 0) {
            return $this->productor->getP($id);
        }
    }


    public function update(ProductorUpdateRequest $request, $id)
    {
        if ($id > 0) {
            return $this->productor->updateP($request, $id);
        }
    }


    public function destroy($id)
    {
        if ($id > 0) {
            return $this->productor->delete($id);
        }
    }

    public function getDeudores()
    {
        $productores = $this->productor->getProductoresDeudores()
            ->paginate(10);
        $totalPrestamosProveedor = $this->productor->getNumeroProductoresDeudores()->get();
        return view('modulos.Reportes.productoresDeudores.deudores', compact('productores','totalPrestamosProveedor'));
    }

    public function getDeudas($id)
    {
        $deudas = $this->productor->getDeudas($id)->paginate(10);
        $total = $this->productor->getTotalDeudas($id);

        return view('modulos.Reportes.productoresDeudores.detalleDeudas', compact('deudas', 'total'));
    }

    public function reporteCajasEntregadasDevueltas(Request $request)
    {

        $cajas = $this->productor->getResumenEntregas()
            ->paginate(10);

        return view('modulos.Reportes.CajasEntregadasDevueltas.cajasEntregadasDevueltas', compact('cajas'));
    }

    public
    function detallePrestamo($rut, $tipo, $temporada)
    {
        $cajas = $this->productor->getDetallePrestamo($rut, $tipo, $temporada)->paginate(10);

        return view('modulos.Reportes.CajasEntregadasDevueltas.detalleCajasEntregadasDevueltas', compact('cajas'));
    }

    public
    function detallePrestamoProveedor($rut, $tipo, $temporada)
    {
        $cajas = $this->productor->getDetallePrestamoProveedor($rut, $tipo, $temporada)->paginate(10);

        return view('modulos.Reportes.CajasEntregadasDevueltas.detalleCajasEntregadasDevueltas', compact('cajas'));
    }

    //Filtros

    public function reporteCajasEntregadasDevueltasFiltro(Request $request)
    {
        if ($request->has("busquedaReporteRut")) {
            $cajas = $this->productor->getResumenEntregasRut($request->get('busquedaReporteRut'))
                ->paginate(10);
        }elseif ($request->has("busquedaReporteNombre")){
            $cajas = $this->productor->getResumenEntregasNombre($request->get('busquedaReporteNombre'))
                ->paginate(10);
        }elseif ($request->has("busquedaReporteCodigo")){
            $cajas = $this->productor->getResumenEntregasCodigo($request->get('busquedaReporteCodigo'))
                ->paginate(10);
        }

        return view('modulos.Reportes.CajasEntregadasDevueltas.cajasEntregadasDevueltas', compact('cajas'));
    }
}
