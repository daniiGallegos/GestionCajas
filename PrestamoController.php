<?php

namespace App\Http\Controllers;

use App\Http\Requests\PrestamoCreateRequest;
use App\Http\Requests\PrestamoUpdateRequest;
use App\Models\Productor;
use App\Models\Proveedor;
use App\Service\DetallePrestamoPendienteService;
use App\Service\DetalleService;
use Illuminate\Http\Exceptions\HttpResponseException;
use App\Service\PrestamoPendienteService;
use App\Service\CajaService;
use App\Service\PrestamoService;
use App\Service\ProductorService;
use App\Service\ProveedorService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PrestamoController extends Controller
{
    protected $prestamo;
    protected $productor;
    protected $proveedor;
    protected $prestamoPendiente;
    protected $caja;
    protected $detalle;
    protected $detallePP;


    public function __construct(
        PrestamoService $prest,
        ProductorService $prod,
        ProveedorService $prov,
        CajaService $caja,
        DetalleService $detalle,
        DetallePrestamoPendienteService $detallePendiente,
        PrestamoPendienteService $pp)

    {
        $this->prestamo = $prest;
        $this->proveedor = $prov;
        $this->productor = $prod;
        $this->prestamoPendiente = $pp;
        $this->caja = $caja;
        $this->detalle = $detalle;
        $this->detallePP = $detallePendiente;

    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        // Get All Data
        $prestamos = $this->prestamo->getPrestamos()
            ->FechaPrestamo($request->get('busquedaFecha'))
            ->NroGuiaPrestamo($request->get('busquedaNroGuia'))
            ->TipoPrestamo($request->get('busquedaTipo'))
            ->TemporadaPrestamo($request->get('busquedaTemporada'))
            ->paginate(15);

        $provs = $this->proveedor->getDatosProveedores();

        $prods = $this->productor->getDatosProductores();

        $clientes = $this->productor->getDatosClientes();

        $cajas = $this->caja->getDatosCajas();

        return view('modulos.Prestamo.index', compact('prestamos', 'provs', 'prods', 'cajas', 'clientes'));
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Http\Response
     */
    public function store(PrestamoCreateRequest $request)
    {
        if ($request->ajax()) {
            //Verifica si existe prestamo pendiente
            $guardar = $this->prestamo->save($request);

            $existePP = $this->prestamoPendiente->existsPending($request['id_prod'], $request['id_prov']);

            //Validacion para crear prestamo pendiente
            if ($existePP == false) {
                $this->prestamoPendiente->save($request['id_prod'], $request['id_prov']);
            }

            return $guardar;
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
        //
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param int $id
     * @return \Illuminate\Http\Response
     */
    public function edit($id)
    {
        return $this->prestamo->edit($id);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param \Illuminate\Http\Request $request
     * @param int $id
     * @return \Illuminate\Http\Response
     */
    public function update(PrestamoUpdateRequest $request, $id)
    {
        return $this->prestamo->update($request, $id);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param int $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        //Busca todos los detalles que tiene el prestamo
        $inf = $this->prestamo->getDetalles($id);

        //Obtener el prestamo
        $pres = $this->prestamo->getPrestamo($id);

        //Verifica si tiene detalles el prestamo
        if ($inf->count()) {
            //Encontrar el codigo del prestamo pendiente
            $presPend = $this->prestamoPendiente->exists($pres['id_prod'], $pres['id_prov']);

            //verifica si existe un prestamo pendiente
            if ($presPend == null) {
                //Sino existe crea el pp
                $this->prestamoPendiente->save($pres['id_prod'], $pres['id_prov']);
                $presPend = $this->prestamoPendiente->exists($pres['id_prod'], $pres['id_prov']);
            }

            //Ingreso
            if ($pres['tipo'] == 'Ingreso') {
                //Ingreso de un cliente o productor
                if ($pres['id_prod'] != null) {
                    $tipoProd = $this->productor->findProductor($pres['id_prod']);

                    //Eliminar cada detalle
                    foreach ($inf as $datos) {
                        if ($datos->delete_det == 0) {
                            //Actualizar todos los detalles a 1 (delete)
                            $this->detalle->deleteDetalle($datos->id_det);

                            //Decrementar cantidad detalle pp
                            $detallePend = $this->detallePP->existeCajaPrestada($presPend ['id_pp'], $datos->id_caja);
                            if ($detallePend != null) {
                                $this->detallePP->decrementCantidadDevuelta($detallePend->id_dpp, $datos->cant_total);

                                //Verificar si cant prestada = cantidad devuelta
                                $verificarDetallePP = $this->detallePP->existeCajaPrestada($presPend->id_pp, $datos->id_caja);
                                $this->verificarDetallePP($verificarDetallePP);

                                //Verificar si es productor de valle frio, si lo es la cant dev disminuye
                                if ($tipoProd->valle_frio == true) { //Es valle frio
                                    //obtener el prestamo del proveedor valle frio
                                    $presPendProv = $this->prestamoPendiente->existsProveedor( 1);

                                    //buscar el detallePP del proveedor y disminuir la cantidad
                                    $detallePendProv = $this->detallePP->existeCajaPrestada($presPendProv ['id_pp'], $datos->id_caja);
                                    if ($detallePendProv != null) {
                                        $this->detallePP->decrementCantidadDevuelta($detallePendProv->id_dpp, $datos->cant_total);
                                    }
                                }

                            } else {
                                //No hay detalle para esa caja y pp Agregar detalle para ese prestamo pendiente
                                $this->detallePP->saveDetallePPProductorEgreso($presPend->id_pp, $datos->id_caja, $datos->cant_total);
                            }

                            //Incrementar o decrementar stock cajas
                            if ($tipoProd->tipo_prod == false) { // Ingreso cliente Productor
                                if ($tipoProd->valle_frio == false) { //No Va a valle frio
                                    //Decrementar cantidad caja
                                    $this->caja->decrement($datos->id_caja, $datos->cant_total);
                                }
                            } else {//Ingreso cliente
                                //Decrementar cantidad caja
                                $this->caja->decrement($datos->id_caja, $datos->cant_total);
                            }
                        }
                    }
                    //Es Ingreso- proveedor
                } else {
                    foreach ($inf as $datos) {
                        if ($datos->delete_det == 0) {
                            //Actualizar todos los detalles a 1 (delete)
                            $this->detalle->deleteDetalle($datos->id_det);

                            //Decrementar cantidad detalle pp
                            $detallePend = $this->detallePP->existeCajaPrestada($presPend['id_pp'], $datos->id_caja);
                            if ($detallePend != null) {
                                $this->detallePP->decrementCantidadPrestada($detallePend->id_dpp, $datos->cant_total);

                                //Verificar si cant prestada = cantidad devuelta
                                $verificarDetallePP = $this->detallePP->existeCajaPrestada($presPend->id_pp, $datos->id_caja);
                                $this->verificarDetallePP($verificarDetallePP);

                            } else {
                                //No hay detalle para esa caja y pp Agregar detalle para ese prestamo pendiente
                                $this->detallePP->saveDetallePPProveedorEgreso($presPend->id_pp, $datos->id_caja, $datos->cant_total);
                            }

                            //Decrementar cantidad caja
                            $this->caja->decrement($datos->id_caja, $datos->cant_total);
                        }
                    }
                }
                //egreso
            } else {
                //Egreso de un cliente o productor
                if ($pres['id_prod'] != null) {
                    foreach ($inf as $datos) {
                        if ($datos->delete_det == 0) {
                            //Actualizar todos los detalles a 1 (delete)
                            $this->detalle->deleteDetalle($datos->id_det);

                            //Decrementar cantidad detalle pp
                            $detallePend = $this->detallePP->existeCajaPrestada($presPend['id_pp'], $datos->id_caja);
                            if ($detallePend != null) {
                                $this->detallePP->decrementCantidadPrestada($detallePend->id_dpp, $datos->cant_total);

                                //Verificar si cant prestada = cantidad devuelta
                                $verificarDetallePP = $this->detallePP->existeCajaPrestada($presPend->id_pp, $datos->id_caja);
                                $this->verificarDetallePP($verificarDetallePP);

                            } else {
                                //No hay detalle para esa caja y pp Agregar detalle para ese prestamo pendiente
                                $this->detallePP->saveDetallePPProductorIngreso($presPend->id_pp, $datos->id_caja, $datos->cant_total);
                            }

                            //Decrementar cantidad caja
                            $this->caja->increment($datos->id_caja, $datos->cant_total);

                        }
                    }
                    //Es Egreso de proveedor
                } else {
                    foreach ($inf as $datos) {
                        if ($datos->delete_det == 0) {
                            //Actualizar todos los detalles a 1 (delete)
                            $this->detalle->deleteDetalle($datos->id_det);

                            //Decrementar cantidad detalle pp
                            $detallePend = $this->detallePP->existeCajaPrestada($presPend['id_pp'], $datos->id_caja);
                            if ($detallePend != null) {
                                $this->detallePP->decrementCantidadDevuelta($detallePend->id_dpp, $datos->cant_total);

                                //Verificar si cant prestada = cantidad devuelta
                                $verificarDetallePP = $this->detallePP->existeCajaPrestada($presPend->id_pp, $datos->id_caja);
                                $this->verificarDetallePP($verificarDetallePP);

                            } else {
                                //No hay detalle para esa caja y pp Agregar detalle para ese prestamo pendiente
                                $this->detallePP->saveDetallePPProveedorIngreso($presPend->id_pp, $datos->id_caja, $datos->cant_total);
                            }

                            //Incrementar cantidad caja
                            $this->caja->increment($datos->id_caja, $datos->cant_total);

                        }
                    }
                }

            }
            //Revisar si el prestamo pendiente tiene detalles pendientes
            //Buscar sus detalles
            $detallesPP = $this->detallePP->getDetallesPrestamoPendiente($presPend->id_pp);

            if ($detallesPP->isEmpty()) {
                //Si no tiene se elimina el prestamo pendiente
                $this->prestamoPendiente->delete($presPend->id_pp);
            }

        }
        return $this->prestamo->delete($id);

    }


    public function verificarDetallePP($verificarDetallePP)
    {
        if ($verificarDetallePP != null) {
            if ($verificarDetallePP->cant_prest === $verificarDetallePP->cant_devuelta) {
                //Eliminar detalle PP
                $this->detallePP->deleteDetallePP($verificarDetallePP->id_dpp);
            }
        }
    }

    //Filtros

    public function filtroPrestamo(Request $request)
    {
        if ($request->has("busquedaNombreProductor")) {
            $prestamos = $this->prestamo->filtroDatosProductor($request->get('busquedaNombreProductor'))
                ->paginate(10);
            $provs = $this->proveedor->getDatosProveedores();
            $prods = $this->productor->getDatosProductores();
            $clientes = $this->productor->getDatosClientes();
            $cajas = $this->caja->getDatosCajas();
        } elseif ($request->has("busquedaNombreProveedor")) {
            $prestamos = $this->prestamo->filtroDatosProveedor($request->get('busquedaNombreProveedor'))
                ->paginate(10);
            $provs = $this->proveedor->getDatosProveedores();
            $prods = $this->productor->getDatosProductores();
            $clientes = $this->productor->getDatosClientes();
            $cajas = $this->caja->getDatosCajas();

        }
        return view('modulos.Prestamo.index', compact('prestamos', 'provs', 'prods', 'cajas', 'clientes'));

    }
//Consulta para autocompletar campos del formulario del productor, proveedor y cliente.
    public function autocompleteP(Request $request)
    {
        $data = Productor::select("id_prod", "razon_prod")
            ->where('delete_prod', '0')
            ->where('tipo_prod','0')
            ->where("razon_prod", "ILIKE", "%{$request->input('query')}%") //ilike es como un ignore case
            ->get();
        return response()->json($data);
    }
    public function autocompleteProv(Request $request)
    {
        $data = Proveedor::select("id_prov", "razon")
            ->where('delete_prov', '0')
            ->where("razon", "ILIKE", "%{$request->input('query')}%") //ilike es como un ignore case
            ->get();
        return response()->json($data);
    }
    public function autocompleteC(Request $request)
    {
        $data = Productor::select("id_prod", "razon_prod")
            ->where('delete_prod', '0')
            ->where('tipo_prod','1')
            ->where("razon_prod", "ILIKE", "%{$request->input('query')}%") //ilike es como un ignore case
            ->get();
        return response()->json($data);
    }
}
