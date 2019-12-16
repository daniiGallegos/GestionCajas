<?php

namespace App\Http\Controllers;

use App\Http\Requests\DetalleCreateRequest;
use App\Http\Requests\DetalleUpdateRequest;
use App\Service\CajaService;
use App\Service\DetallePrestamoPendienteService;
use App\Service\DetalleService;
use App\Service\PrestamoPendienteService;
use App\Service\PrestamoService;
use App\Service\ProductorService;
use App\Service\ProveedorService;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Request;
use phpDocumentor\Reflection\Types\Null_;

class DetalleController extends Controller
{

    protected $detalle;
    protected $prestamo;
    protected $caja;
    protected $prestamoPendiente;
    protected $detallePP;
    protected $productor;
    protected $proveedor;

    public function __construct(
        DetalleService $detalle,
        PrestamoService $prestamo,
        CajaService $caja,
        PrestamoPendienteService $prestamoPendiente,
        DetallePrestamoPendienteService $detallePP,
        ProductorService $productor,
        ProveedorService $proveedor)
    {
        $this->detalle = $detalle;
        $this->prestamo = $prestamo;
        $this->caja = $caja;
        $this->prestamoPendiente = $prestamoPendiente;
        $this->detallePP = $detallePP;
        $this->productor = $productor;
        $this->proveedor = $proveedor;
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        //
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
    public function store(DetalleCreateRequest $request)
    {
        //
        if ($request->ajax()) {
            // Verificar que tipo de prestamo es: Ingreso o Egreso
            $pres = $this->prestamo->getPrestamo($request->get('id_pres'));

            if ($pres != null) {

                if ($pres->tipo === 'Ingreso') {

                    //Verificar si el proveedor/productor ya existe en prestamo pendiente
                    $prestamoPendiente = $this->prestamoPendiente->exists($pres->id_prod, $pres->id_prov);

                    if ($prestamoPendiente != null) {
                        //Existe
                        //Verificar si es proveedor o productor
                        if ($prestamoPendiente->tipo_pp == 'Proveedor') {
                            //Sumar stock
                            $this->caja->increment($request->get('id_caja'), $request->get('cant_total'));
                            //Es proveedor
                            //Buscar si existe un detalle con la caja que se está agregando
                            $detallePP = $this->detallePP->existeCajaPrestada($prestamoPendiente->id_pp, $request->get('id_caja'));
                            if ($detallePP != null) {
                                //Si hay detalle para esa caja y pp
                                //Es ingreso y proveedor por lo tanto hay que incrementar detalle pp -> cantidad prestada
                                $this->detallePP->incrementCantidadPrestada($detallePP->id_dpp, $request->get('cant_total'));

                                //Verificar si cant prestada = cantidad devuelta
                                $verificarDetallePP = $this->detallePP->existeCajaPrestada($detallePP->id_pp, $detallePP->id_caja);
                                $this->verificarDetallePP($verificarDetallePP);

                            } else {
                                //No hay detalle para esa caja y pp
                                //Agregar detalle para ese prestamo pendiente
                                $this->detallePP->saveDetallePPProveedorIngreso($prestamoPendiente->id_pp, $request->get('id_caja'), $request->get('cant_total'));
                            }
                        }

                        if ($prestamoPendiente->tipo_pp == 'Productor' || $prestamoPendiente->tipo_pp == 'Cliente') {
                            //Es productor
                            //Verificar si prodcutor es de valle frio
                            $prod = $this->productor->findProductor($pres->id_prod);

                            if (!$prod->valle_frio) {
                                // No es de valle frio
                                //Sumar stock
                                $this->caja->increment($request->get('id_caja'), $request->get('cant_total'));
                            }

                            $detallePP = $this->detallePP->existeCajaPrestada($prestamoPendiente->id_pp, $request->get('id_caja'));

                            if ($detallePP != null) {
                                //Si hay detalle para esa caja y pp
                                //Es ingreso y productor por lo tanto hay que incrementar cantidad devuelta


                                //no es valle frio
                                $this->detallePP->incrementCantidadDevuelta($detallePP->id_dpp, $request->get('cant_total'));
                                if ($prod->valle_frio) {

                                    //Incrementar cantidad devuelta proveedor
                                    //Buscar detallepp de valle frio
                                    // Obtener proveedor
                                    $prov = $this->proveedor->getListaProveedores()[0];

                                    if ($prov != null) {

                                        //Buscar prestamo proveedor
                                        $prestamoPProveedor = $this->prestamoPendiente->exists(null, $prov->id_prov);

                                        if ($prestamoPProveedor != null) {
                                            //Buscar detalle
                                            $detallePPProveedor = $this->detallePP->existeCajaPrestada($prestamoPProveedor->id_pp, $request->get('id_caja'));
                                            if ($detallePPProveedor != null) {
                                                $this->detallePP->incrementCantidadDevuelta($detallePPProveedor->id_dpp, $request->get('cant_total'));
                                                $this->verificarDetallePP($detallePPProveedor);
                                            }
                                        } else {
                                            //Crear prestamo
                                            $this->prestamoPendiente->save(null, $prov->id_prov);

                                            //Obtener prestamo pendiente proveedor
                                            $nuevoPrestamoPProveedor = $this->prestamoPendiente->exists(null, $prov->id_prov);
                                            //Crear detalle para la caja
                                            $this->detallePP->saveDetallePPProveedorEgreso($nuevoPrestamoPProveedor->id_pp, $request->get('id_caja'), $request->get('cant_total'));
                                        }

                                    }

                                }


                                //Verificar si cant prestada = cantidad devuelta
                                $verificarDetallePP = $this->detallePP->existeCajaPrestada($detallePP->id_pp, $detallePP->id_caja);
                                $this->verificarDetallePP($verificarDetallePP);

                            } else {
                                //No hay detalle para esa caja y pp
                                //Agregar detalle para ese prestamo pendiente
                                $this->detallePP->saveDetallePPProductorIngreso($prestamoPendiente->id_pp, $request->get('id_caja'), $request->get('cant_total'));

                                if ($prod->valle_frio) {
                                    $prov = $this->proveedor->getListaProveedores()[0];

                                    if ($prov != null) {
                                        //Buscar prestamo proveedor
                                        $prestamoPProveedor = $this->prestamoPendiente->exists(null, $prov->id_prov);

                                        if ($prestamoPProveedor != null) {
                                            //Buscar detalle
                                            $detallePPProveedor = $this->detallePP->existeCajaPrestada($prestamoPProveedor->id_pp, $request->get('id_caja'));
                                            if ($detallePPProveedor != null) {
                                                $this->detallePP->incrementCantidadDevuelta($detallePPProveedor->id_dpp, $request->get('cant_total'));
                                            } else {
                                                $this->detallePP->saveDetallePPProveedorEgreso($prestamoPProveedor->id_pp, $request->get('id_caja'), $request->get('cant_total'));
                                            }
                                        } else {
                                            //Crear prestamo
                                            $this->prestamoPendiente->save(null, $prov->id_prov);

                                            //Obtener prestamo pendiente proveedor
                                            $nuevoPrestamoPProveedor = $this->prestamoPendiente->exists(null, $prov->id_prov);
                                            //Crear detalle para la caja
                                            $this->detallePP->saveDetallePPProveedorEgreso($nuevoPrestamoPProveedor->id_pp, $request->get('id_caja'), $request->get('cant_total'));
                                        }
                                    }
                                }


                            }
                        }


                    } else {

                        //Productor o Cliente
                        if ($pres->id_prod != null) {
                            //Verificar si es productor o cliente

                            // Crear prestamo pendiente
                            $pp = $this->prestamoPendiente->save($pres->id_prod, null);

                            //Crear detalle pp
                            $this->detallePP->saveDetallePPProductorIngreso($pp->id_pp, $request->get('id_caja'), $request->get('cant_total'));

                            //Verificar si es ingreso productor valle frio, de ser así hay que hacer un egreso autormático
                            $prod = $this->productor->findProductor($pres->id_prod);
                            if ($prod != null) {
                                if ($prod->valle_frio) {
                                    // Es prodcutor valle frio
                                    //Hay que hacer un egreso automatico a proveedor
                                    $prov = $this->proveedor->getListaProveedores()[0];
                                    if ($prov != null) {
                                        //Buscar prestamo proveedor
                                        $prestamoPProveedor = $this->prestamoPendiente->exists(null, $prov->id_prov);

                                        if ($prestamoPProveedor != null) {
                                            //Buscar detalle
                                            $detallePPProveedor = $this->detallePP->existeCajaPrestada($prestamoPProveedor->id_pp, $request->get('id_caja'));
                                            if ($detallePPProveedor != null) {
                                                $this->detallePP->incrementCantidadDevuelta($detallePPProveedor->id_dpp, $request->get('cant_total'));
                                            }
                                        } else {
                                            //Crear prestamo
                                            $this->prestamoPendiente->save(null, $prov->id_prov);

                                            //Obtener prestamo pendiente proveedor
                                            $nuevoPrestamoPProveedor = $this->prestamoPendiente->exists(null, $prov->id_prov);
                                            //Crear detalle para la caja
                                            $this->detallePP->saveDetallePPProveedorEgreso($nuevoPrestamoPProveedor->id_pp, $request->get('id_caja'), $request->get('cant_total'));
                                        }
                                    }
                                }
                            }

                        }

                        //Proveedor
                        if ($pres->id_prov != null) {
                            // Crear prestamo pendiente
                            $ppProv = $this->prestamoPendiente->save(null, $pres->id_prov);
                            //Crear detalle pp
                            $this->detallePP->saveDetallePPProveedorIngreso($ppProv->id_pp, $request->get('id_caja'), $request->get('cant_total'));
                        }

                    }
                }
                if ($pres->tipo === 'Egreso') {
                    //Verificar si hay stock suficiente
                    $box = $this->caja->getCaja($request->get('id_caja'));
                    if ($request->get('cant_total') > $box->stock_total) {
                        throw new HttpResponseException(response()->json(['errors' => ['No hay stock suficiente para prestar la cantidad ingresada']])); //-> ternna los errores por separado con su respectivo nombre
                    }
                    //Restar stock
                    $this->caja->decrement($request->get('id_caja'), $request->get('cant_total'));

                    //Verificar si el proveedor/productor ya existe en prestamo pendiente
                    $prestamoPendiente = $this->prestamoPendiente->exists($pres->id_prod, $pres->id_prov);
                    if ($prestamoPendiente) {
                        //Existe
                        //Verificar si es proveedor o productor
                        if ($prestamoPendiente->tipo_pp == 'Proveedor') {
                            //Es proveedor
                            //Buscar si existe un detalle con la caja que se está agregando
                            $detallePP = $this->detallePP->existeCajaPrestada($prestamoPendiente->id_pp, $request->get('id_caja'));
                            if ($detallePP) {
                                //Si hay detalle para esa caja y pp
                                //Es Egreso y proveedor por lo tanto hay que incrementar cantidad devuelta
                                $this->detallePP->incrementCantidadDevuelta($detallePP->id_dpp, $request->get('cant_total'));

                                //Verificar si cant prestada = cantidad devuelta
                                $verificarDetallePP = $this->detallePP->existeCajaPrestada($detallePP->id_pp, $detallePP->id_caja);
                                $this->verificarDetallePP($verificarDetallePP);
                            } else {
                                //No hay detalle
                                //Agregar nuevo detalle
                                $this->detallePP->saveDetallePPProveedorEgreso($prestamoPendiente->id_pp, $request->get('id_caja'), $request->get('cant_total'));
                            }
                        }

                        if ($prestamoPendiente->tipo_pp == 'Productor' || $prestamoPendiente->tipo_pp == 'Cliente') {
                            //Es productor
                            //Buscar si existe un detalle con la caja que se está agregando
                            $detallePP = $this->detallePP->existeCajaPrestada($prestamoPendiente->id_pp, $request->get('id_caja'));
                            if ($detallePP) {
                                //Si hay detalle para esa caja y pp
                                //Es Egreso y productor por lo tanto hay que incrementar cantidad prestada
                                $this->detallePP->incrementCantidadPrestada($detallePP->id_dpp, $request->get('cant_total'));

                                //Verificar si cant prestada = cantidad devuelta
                                $verificarDetallePP = $this->detallePP->existeCajaPrestada($detallePP->id_pp, $detallePP->id_caja);
                                $this->verificarDetallePP($verificarDetallePP);
                            } else {
                                //No hay detalle
                                //Agregar nuevo detalle
                                $this->detallePP->saveDetallePPProductorEgreso($prestamoPendiente->id_pp, $request->get('id_caja'), $request->get('cant_total'));
                            }
                        }
                    } else {
                        //Crear prestamo
                        if ($pres->id_prod != null) {
                            // Es egreso productor/cliente

                            //Crear prestamo
                            $ppProd = $this->prestamoPendiente->save($pres->id_prod, null);
                            //Crear detalle
                            $this->detallePP->saveDetallePPProductorEgreso($ppProd->id_dd, $request->get('id_caja'), $request->get('cant_total'));
                        }
                        if ($pres->id_prov != null) {
                            // Es egreso productor
                            //Crear prestamo
                            $ppProv = $this->prestamoPendiente->save(null, $pres->id_prov);
                            //Crear detalle
                            $this->detallePP->saveDetallePPProveedorEgreso($ppProv->id_pp, $request->get('id_caja'), $request->get('cant_total'));
                        }
                    }
                }

                //Verificar si cant_prestada == cant_devuelta
                $prestamoPendiente = $this->prestamoPendiente->exists($pres->id_prod, $pres->id_prov);
                if ($prestamoPendiente) {
                    //Buscar sus detalles
                    $detallesPP = $this->detallePP->getDetallesPrestamoPendiente($prestamoPendiente->id_pp);

                    //Verificar detalle -> cant_prest === cant_devuelta
                    $this->verificarDetallesPrestamoPendiente($detallesPP, $prestamoPendiente->id_pp);

                }
            }

            // Verificar si existe un detalle con la misma caja para el mismo prestamo
            $det = $this->detalle->getDetalleByIdDetalleAndIdPrestamoAndIdCaja($pres->id_pres, $request->get('id_caja'));
            if (sizeof($det) > 0) {
                // Actualizar detalle
                return $this->detalle->updateDetalleOnSave($det[0]->id_det, ($det[0]->cant_total + $request->get('cant_total')));
            } else {
                return $this->detalle->save($request);
            }

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
        //
        return $this->detalle->edit($id);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param \Illuminate\Http\Request $request
     * @param int $id
     * @return \Illuminate\Http\Response
     */
    public function update(DetalleUpdateRequest $request, $id)
    {
        // Falta verificar si cambia la caja
        //
        if ($request->ajax()) {
            //obtener detalle
            $det = $this->detalle->getDetalle($id);
            if ($det != null) {
                //Verificar tipo de prestamo
                $pres = $this->prestamo->getPrestamo($det->id_pres);
                if ($pres != null) {

                    //Verificar si el proveedor/productor ya existe en prestamo pendiente
                    $prestamoPendiente = $this->verificarPrestamoPendienteExistente($pres->id_prod, $pres->id_prov);

                    if ($prestamoPendiente != null) {

                        $cantidadActual = $det->cant_total;
                        $nuevaCantidad = $request->get('cant_total');

                        //Verificar si la caja cambió
                        if ($det->id_caja == $request->get('id_caja')) {
                            // La caja no cambió
                            if ($pres->tipo == 'Ingreso') {
                                //Incrementar stock si nuevaCantidad > cantidad Actual
                                //Sino decrementar

                                //Verificar si es prodcutor y valle frio
                                //Verificar si es proveedor o productor
                                if ($prestamoPendiente->tipo_pp == 'Productor') {
                                    if ($nuevaCantidad > $cantidadActual) {
                                        //Actualizar detalle pendiente
                                        //Buscar detalle
                                        $detallePP = $this->verificarDetallePPExistente($prestamoPendiente->id_pp, $request->get('id_caja'));

                                        if ($detallePP != null) {

                                            $this->detallePP->incrementCantidadDevuelta($detallePP->id_dpp, ($nuevaCantidad - $cantidadActual));

                                            //Verificar si prodcutor es de valle frio
                                            $prod = $this->productor->findProductor($pres->id_prod);
                                            if ($prod->valle_frio) {
                                                // Es de valle
                                                //Buscar proveeedor
                                                $prov = $this->proveedor->getListaProveedores()[0];
                                                if ($prov != null) {

                                                    //Buscar prestamo pp proveedor
                                                    $prestamoPProveedor = $this->prestamoPendiente->exists(null, $prov->id_prov);
                                                    if ($prestamoPProveedor != null) {
                                                        //Buscar detalle p
                                                        $detallePProveedor = $this->detallePP->existeCajaPrestada($prestamoPProveedor->id_pp, $request->get('id_caja'));
                                                        if ($detallePProveedor != null) {
                                                            // Existe
                                                            //Actualizar cantidad devuelta proveedor
                                                            $this->detallePP->incrementCantidadDevuelta($detallePProveedor->id_dpp, ($nuevaCantidad - $cantidadActual));

                                                            //Verificar cant prest = cant devuelta en proveedor
                                                            $this->verificarDetallePP($detallePProveedor);
                                                        } else {
                                                            //No hay detalle
                                                            //Crear detalle
                                                            $this->detallePP->saveDetallePPProveedorEgreso($prestamoPProveedor->id_pp, $request->get('id_caja'), $request->get('cant_total'));
                                                        }
                                                    } else {
                                                        //Crear prestamo
                                                        $this->prestamoPendiente->save(null, $prov->id_prov);

                                                        //Obtener prestamo pendiente proveedor
                                                        $nuevoPrestamoPProveedor = $this->prestamoPendiente->exists(null, $prov->id_prov);

                                                        //Crear detalle para la caja
                                                        $this->detallePP->saveDetallePPProveedorEgreso($nuevoPrestamoPProveedor->id_pp, $request->get('id_caja'), $request->get('cant_total'));

                                                    }

                                                }
                                            } else {
                                                //Incrementar stock
                                                $this->caja->increment($request->get('id_caja'), ($nuevaCantidad - $cantidadActual));
                                            }

                                        }

                                        //Actualizar dato cantidad tototal en el detalle
                                        //Verificar cant_prestada = cant_devuelta
                                        $this->verificarDetallePP($detallePP);
                                        return $this->detalle->update($request, $id);
                                    }
                                    if ($nuevaCantidad == $cantidadActual) {
                                        throw new HttpResponseException(response()->json(['errors' => ['La cantidad ingresada es igual a la ya almacenada en la base de datos']])); //-> ternna los errores por separado con su respectivo nombre
                                    }

                                    if ($nuevaCantidad < $cantidadActual) {
                                        //Actualizar detalle pendiente
                                        //Buscar detalle
                                        $detallePP = $this->detallePP->existeCajaPrestada($prestamoPendiente->id_pp, $request->get('id_caja'));
                                        if ($detallePP != null) {
                                            $this->detallePP->decrementCantidadDevuelta($detallePP->id_dpp, ($cantidadActual - $nuevaCantidad));

                                            //Verificar si prodcutor es de valle frio
                                            $prod = $this->productor->findProductor($pres->id_prod);
                                            if ($prod->valle_frio) {
                                                //Buscar proveeedor
                                                $prov = $this->proveedor->getListaProveedores()[0];
                                                if ($prov != null) {

                                                    //Buscar prestamo pp proveedor
                                                    $prestamoPProveedor = $this->prestamoPendiente->exists(null, $prov->id_prov);
                                                    if ($prestamoPProveedor != null) {
                                                        //Buscar detalle p
                                                        $detallePProveedor = $this->detallePP->existeCajaPrestada($prestamoPProveedor->id_pp, $request->get('id_caja'));
                                                        if ($detallePProveedor != null) {
                                                            // Existe
                                                            //Actualizar cantidad devuelta proveedor
                                                            $this->detallePP->decrementCantidadDevuelta($detallePProveedor->id_dpp, ($cantidadActual - $nuevaCantidad));
                                                        } else {
                                                            //No hay detalle
                                                            //Crear detalle
                                                            $this->detallePP->saveDetallePPProveedorEgreso($prestamoPProveedor->id_pp, $request->get('id_caja'), $request->get('cant_total'));
                                                        }
                                                    } else {
                                                        //Crear prestamo
                                                        $this->prestamoPendiente->save(null, $prov->id_prov);

                                                        //Obtener prestamo pendiente proveedor
                                                        $nuevoPrestamoPProveedor = $this->prestamoPendiente->exists(null, $prov->id_prov);

                                                        //Crear detalle para la caja
                                                        $this->detallePP->saveDetallePPProveedorEgreso($nuevoPrestamoPProveedor->id_pp, $request->get('id_caja'), $request->get('cant_total'));

                                                    }

                                                }

                                            } else {
                                                //Decrementar stock
                                                $this->caja->decrement($request->get('id_caja'), ($cantidadActual - $nuevaCantidad));
                                            }


                                        }
                                        //Verificar cant_prestada = cant_devuelta
                                        $this->verificarDetallePP($detallePP);
                                        //Actualizar dato cantidad tototal en el detalle
                                        return $this->detalle->update($request, $id);
                                    }
                                }

                                if ($prestamoPendiente->tipo_pp == 'Cliente') {
                                    if ($nuevaCantidad > $cantidadActual) {
                                        //Actualizar stock
                                        $this->caja->increment($det->id_caja, ($nuevaCantidad - $cantidadActual));
                                        //Actualizar detalle pendiente
                                        //Buscar detalle
                                        $detallePP = $this->detallePP->existeCajaPrestada($prestamoPendiente->id_pp, $request->get('id_caja'));
                                        if ($detallePP != null) {
                                            $this->detallePP->incrementCantidadDevuelta($detallePP->id_dpp, ($nuevaCantidad - $cantidadActual));
                                        } else {
                                            //Crear detalle
                                            $this->detallePP->saveDetallePPProductorIngreso($prestamoPendiente->id_pp, $request->get('id_caja'), $nuevaCantidad - $cantidadActual);
                                        }

                                        $detallePP = $this->detallePP->existeCajaPrestada($prestamoPendiente->id_pp, $request->get('id_caja'));

                                        //Actualizar dato cantidad tototal en el detalle
                                        //Verificar cant_prestada = cant_devuelta
                                        $this->verificarDetallePP($detallePP);
                                        return $this->detalle->update($request, $id);
                                    }
                                    if ($nuevaCantidad == $cantidadActual) {
                                        throw new HttpResponseException(response()->json(['errors' => ['La cantidad ingresada es igual a la ya almacenada en la base de datos']])); //-> ternna los errores por separado con su respectivo nombre
                                    }

                                    if ($nuevaCantidad < $cantidadActual) {
                                        //Decrementar stock
                                        $box = $this->caja->getCaja($det->id_caja);
                                        if ($box->stock_total - ($cantidadActual - $nuevaCantidad) < 0) {
                                            throw new HttpResponseException(response()->json(['errors' => ['No hay stock suficiente']])); //-> ternna los errores por separado con su respectivo nombre
                                        }
                                        //Restar stock
                                        $this->caja->decrement($det->id_caja, ($cantidadActual - $nuevaCantidad));

                                        //Actualizar detalle pendiente
                                        //Buscar detalle
                                        $detallePP = $this->detallePP->existeCajaPrestada($prestamoPendiente->id_pp, $request->get('id_caja'));
                                        if ($detallePP != null) {
                                            $this->detallePP->decrementCantidadDevuelta($detallePP->id_dpp, ($cantidadActual - $nuevaCantidad));
                                        } else {
                                            //Crear detalle
                                            $this->detallePP->saveDetallePPProductorIngreso($prestamoPendiente->id_pp, $request->get('id_caja'), $cantidadActual - $nuevaCantidad);
                                        }

                                        $detallePP = $this->detallePP->existeCajaPrestada($prestamoPendiente->id_pp, $request->get('id_caja'));

                                        //Verificar cant_prestada = cant_devuelta
                                        $this->verificarDetallePP($detallePP);
                                        //Actualizar dato cantidad tototal en el detalle
                                        return $this->detalle->update($request, $id);
                                    }
                                }

                                if ($prestamoPendiente->tipo_pp == 'Proveedor') {
                                    if ($nuevaCantidad > $cantidadActual) {
                                        //Incrementar stock - diferencia
                                        $this->caja->increment($det->id_caja, ($nuevaCantidad - $cantidadActual));

                                        //Actualizar detalle p
                                        //Buscar detalle
                                        $detallePP = $this->detallePP->existeCajaPrestada($prestamoPendiente->id_pp, $request->get('id_caja'));
                                        if ($detallePP != null) {
                                            $this->detallePP->incrementCantidadPrestada($detallePP->id_dpp, ($nuevaCantidad - $cantidadActual));
                                        } else {
                                            //Crear detalle
                                            $this->detallePP->saveDetallePPProveedorIngreso($prestamoPendiente->id_pp, $request->get('id_caja'), $nuevaCantidad - $cantidadActual);
                                        }

                                        $detallePP = $this->detallePP->existeCajaPrestada($prestamoPendiente->id_pp, $request->get('id_caja'));


                                        //Verificar cant_prestada = cant_devuelta
                                        $this->verificarDetallePP($detallePP);
                                        //Actualizar dato cantidad tototal en el detalle
                                        return $this->detalle->update($request, $id);
                                    }
                                    if ($nuevaCantidad == $cantidadActual) {
                                        throw new HttpResponseException(response()->json(['errors' => ['La cantidad ingresada es igual a la ya almacenada en la base de datos']])); //-> ternna los errores por separado con su respectivo nombre
                                    }

                                    if ($nuevaCantidad < $cantidadActual) {
                                        // Decrementar stock - diferencia
                                        $box = $this->caja->getCaja($det->id_caja);
                                        if ($box->stock_total - ($cantidadActual - $nuevaCantidad) < 0) {
                                            throw new HttpResponseException(response()->json(['errors' => ['No hay stock suficiente']])); //-> ternna los errores por separado con su respectivo nombre
                                        }
                                        //Restar stock
                                        $this->caja->decrement($det->id_caja, ($cantidadActual - $nuevaCantidad));

                                        //Buscar detalle
                                        $detallePP = $this->detallePP->existeCajaPrestada($prestamoPendiente->id_pp, $request->get('id_caja'));
                                        if ($detallePP != null) {
                                            $this->detallePP->decrementCantidadPrestada($detallePP->id_dpp, ($cantidadActual - $nuevaCantidad));
                                        } else {
                                            //Crear detalle
                                            $this->detallePP->saveDetallePPProveedorIngreso($prestamoPendiente->id_pp, $request->get('id_caja'), $cantidadActual - $nuevaCantidad);
                                        }

                                        $detallePP = $this->detallePP->existeCajaPrestada($prestamoPendiente->id_pp, $request->get('id_caja'));


                                        //Verificar cant_prestada = cant_devuelta
                                        $this->verificarDetallePP($detallePP);
                                        //Actualizar dato cantidad tototal en el detalle
                                        return $this->detalle->update($request, $id);
                                    }
                                }

                            }

                            if ($pres->tipo == 'Egreso') {
                                if ($prestamoPendiente->tipo_pp == 'Proveedor') {
                                    //Decrementar stock si nuevaCantidad > cantidad actual
                                    //Sino incrementar

                                    if ($nuevaCantidad > $cantidadActual) {
                                        // Decrementar stock - diferencia
                                        $box = $this->caja->getCaja($det->id_caja);
                                        if ($box->stock_total - ($nuevaCantidad - $cantidadActual) < 0) {
                                            throw new HttpResponseException(response()->json(['errors' => ['No hay stock suficiente']])); //-> ternna los errores por separado con su respectivo nombre
                                        }
                                        //Restar stock
                                        $this->caja->decrement($det->id_caja, ($nuevaCantidad - $cantidadActual));

                                        //Buscar detalle
                                        $detallePP = $this->detallePP->existeCajaPrestada($prestamoPendiente->id_pp, $request->get('id_caja'));
                                        if ($detallePP != null) {
                                            $this->detallePP->incrementCantidadDevuelta($detallePP->id_dpp, ($nuevaCantidad - $cantidadActual));
                                        } else {
                                            //Crear detalle
                                            $this->detallePP->saveDetallePPProveedorEgreso($prestamoPendiente->id_pp, $request->get('id_caja'), $nuevaCantidad - $cantidadActual);
                                        }

                                        $detallePP = $this->detallePP->existeCajaPrestada($prestamoPendiente->id_pp, $request->get('id_caja'));


                                        //Verificar cant_prestada = cant_devuelta
                                        $this->verificarDetallePP($detallePP);
                                        //Actualizar dato cantidad tototal en el detalle
                                        return $this->detalle->update($request, $id);
                                    }
                                    if ($nuevaCantidad == $cantidadActual) {
                                        throw new HttpResponseException(response()->json(['errors' => ['La cantidad ingresada es igual a la ya almacenada en la base de datos']])); //-> ternna los errores por separado con su respectivo nombre
                                    }

                                    if ($nuevaCantidad < $cantidadActual) {

                                        //Incrementar stock - diferencia
                                        $this->caja->increment($det->id_caja, ($cantidadActual - $nuevaCantidad));

                                        //Buscar detalle
                                        $detallePP = $this->detallePP->existeCajaPrestada($prestamoPendiente->id_pp, $request->get('id_caja'));
                                        if ($detallePP != null) {
                                            $this->detallePP->decrementCantidadDevuelta($detallePP->id_dpp, ($cantidadActual - $nuevaCantidad));
                                        } else {
                                            //Crear detalle
                                            $this->detallePP->saveDetallePPProveedorEgreso($prestamoPendiente->id_pp, $request->get('id_caja'), $cantidadActual - $nuevaCantidad);
                                        }

                                        $detallePP = $this->detallePP->existeCajaPrestada($prestamoPendiente->id_pp, $request->get('id_caja'));


                                        //Verificar cant_prestada = cant_devuelta
                                        $this->verificarDetallePP($detallePP);
                                        //Actualizar dato cantidad tototal en el detalle
                                        return $this->detalle->update($request, $id);
                                    }
                                }

                                if ($prestamoPendiente->tipo_pp == 'Productor' || $prestamoPendiente->tipo_pp == 'Cliente') {
                                    //Decrementar stock si nuevaCantidad > cantidad actual
                                    //Sino incrementar

                                    if ($nuevaCantidad > $cantidadActual) {
                                        // Decrementar stock - diferencia
                                        $box = $this->caja->getCaja($det->id_caja);
                                        if ($box->stock_total - ($nuevaCantidad - $cantidadActual) < 0) {
                                            throw new HttpResponseException(response()->json(['errors' => ['No hay stock suficiente']])); //-> ternna los errores por separado con su respectivo nombre
                                        }
                                        //Restar stock
                                        $this->caja->decrement($det->id_caja, ($nuevaCantidad - $cantidadActual));

                                        //Buscar detalle
                                        $detallePP = $this->detallePP->existeCajaPrestada($prestamoPendiente->id_pp, $request->get('id_caja'));
                                        if ($detallePP != null) {
                                            $this->detallePP->incrementCantidadPrestada($detallePP->id_dpp, ($nuevaCantidad - $cantidadActual));
                                        } else {
                                            //Crear detalle
                                            $this->detallePP->saveDetallePPProductorEgreso($prestamoPendiente->id_pp, $request->get('id_caja'), $nuevaCantidad - $cantidadActual);
                                        }

                                        $detallePP = $this->detallePP->existeCajaPrestada($prestamoPendiente->id_pp, $request->get('id_caja'));
                                        //Verificar cant_prestada = cant_devuelta
                                        $this->verificarDetallePP($detallePP);
                                        //Actualizar dato cantidad tototal en el detalle
                                        return $this->detalle->update($request, $id);
                                    }
                                    if ($nuevaCantidad == $cantidadActual) {
                                        throw new HttpResponseException(response()->json(['errors' => ['La cantidad ingresada es igual a la ya almacenada en la base de datos']])); //-> ternna los errores por separado con su respectivo nombre
                                    }

                                    if ($nuevaCantidad < $cantidadActual) {

                                        //Incrementar stock - diferencia
                                        $this->caja->increment($det->id_caja, ($cantidadActual - $nuevaCantidad));

                                        //Buscar detalle
                                        $detallePP = $this->detallePP->existeCajaPrestada($prestamoPendiente->id_pp, $request->get('id_caja'));
                                        if ($detallePP != null) {
                                            $this->detallePP->decrementCantidadPrestada($detallePP->id_dpp, ($cantidadActual - $nuevaCantidad));
                                        } else {
                                            //Crear detalle
                                            $this->detallePP->saveDetallePPProductorEgreso($prestamoPendiente->id_pp, $request->get('id_caja'), $cantidadActual - $nuevaCantidad);
                                        }

                                        $detallePP = $this->detallePP->existeCajaPrestada($prestamoPendiente->id_pp, $request->get('id_caja'));


                                        //Verificar cant_prestada = cant_devuelta
                                        $this->verificarDetallePP($detallePP);
                                        //Actualizar dato cantidad tototal en el detalle
                                        return $this->detalle->update($request, $id);
                                    }
                                }
                            }
                        } else {
                            // La caja cambió
                            // por lo tanto hay que incrementar el stock de la caja anterior
                            // y decrementar el stcok de la nueva caja

                            if ($pres->tipo == 'Ingreso') {
                                // obtener el detalle con caja anterior
                                $detalleAnterior = $this->detalle->getDetalle($request->get('id_det'));
                                $detallePPAnterior = $this->detallePP->existeCajaPrestada($prestamoPendiente->id_pp, $detalleAnterior->id_caja);

                                if ($prestamoPendiente->tipo_pp == 'Productor') {

                                    if ($detallePPAnterior != null && $detalleAnterior != null) {

                                        // verificar si ya existe otro detalle pendiente con la nueva caja
                                        $detallePPNuevo = $this->detallePP->existeCajaPrestada($prestamoPendiente->id_pp, $request->get('id_caja'));

                                        //Verificar si existe otro detalle con la nueva caja
                                        $detalleNuevo = $this->detalle->getDetalleByPrestamoAndCaja($pres->id_pres, $request->get('id_caja'));

                                        if ($detallePPNuevo != null && $detalleNuevo != null) {
                                            //Ya existe un detalle con la caja que se editó
                                            // incrementar cant_total nuevo detalle
                                            $this->detalle->increment($detalleNuevo->id_det, $request->get('cant_total'));

                                            // decrementar cant_total detalle caja anterior
                                            $this->detalle->decrement($detalleAnterior->id_det, $detalleAnterior->cant_total);

                                            //incrementar cant_devuelta nuevo detalle pp
                                            $this->detallePP->incrementCantidadDevuelta($detallePPNuevo->id_dpp, $request->get('cant_total'));

                                            //decrementar cant_devuelta detalle pp anterior
                                            $this->detallePP->decrementCantidadDevuelta($detallePPAnterior->id_dpp, $detalleAnterior->cant_total);

                                            //Verificar si prodcutor es de valle frio
                                            $prod = $this->productor->findProductor($pres->id_prod);
                                            if ($prod->valle_frio) {
                                                // Buscar proveedor
                                                $prov = $this->proveedor->getListaProveedores()[0];
                                                if ($prov != null) {
                                                    //Get prestamo pp
                                                    $prestamoPProveedor = $this->prestamoPendiente->exists(null, $prov->id_prov);
                                                    if ($prestamoPProveedor != null) {
                                                        //Existe prestamo
                                                        // decrementar cantidad devuelta proveedor caja anterior
                                                        $detallePProveedorAnterior = $this->detallePP->existeCajaPrestada($prestamoPProveedor->id_pp, $detallePPAnterior->id_caja);
                                                        if ($detallePProveedorAnterior != null) {
                                                            $this->detallePP->decrementCantidadDevuelta($detallePProveedorAnterior->id_dpp, $detalleAnterior->cant_total);
                                                        }
                                                        // incrementar cantidad devuelta proveedor caja nueva

                                                        $detallePProveedorNuevo = $this->detallePP->existeCajaPrestada($prestamoPProveedor->id_pp, $request->get('id_caja'));
                                                        if ($detallePProveedorNuevo != null) {
                                                            //Existe detalle nuevo
                                                            $this->detallePP->incrementCantidadDevuelta($detallePProveedorNuevo->id_dpp, $request->get('cant_total'));
                                                        } else {
                                                            //No hay detalle
                                                            $this->detallePP->saveDetallePPProveedorEgreso($prestamoPProveedor->id_pp, $request->get('id_caja'), $request->get('cant_total'));
                                                        }

                                                    } else {
                                                        // no existe prestamo
                                                        //Crear prestamo
                                                        $this->prestamoPendiente->save(null, $prov->id_prov);

                                                        //Obtener prestamo pendiente proveedor
                                                        $nuevoPrestamoPProveedor = $this->prestamoPendiente->exists(null, $prov->id_prov);

                                                        //Crear detalle para la caja
                                                        $this->detallePP->saveDetallePPProveedorEgreso($nuevoPrestamoPProveedor->id_pp, $request->get('id_caja'), $request->get('cant_total'));

                                                    }

                                                }


                                            } else {
                                                //Decrementar stock caja anterior
                                                $this->caja->decrement($detalleAnterior->id_caja, $detalleAnterior->cant_total);
                                                //Incrementar stock nueva caja
                                                $this->caja->increment($detalleNuevo->id_caja, $request->get('cant_total'));
                                            }

                                        } else {
                                            // no exite detalle para la nueva caja
                                            //Crear nuevo detalle

                                            $this->detalle->createNewDetalle($pres->id_pres, $request->get('id_caja'), $request->get('cant_total'));

                                            //Crear nuevo detalle pp -> cant_devuelta
                                            $this->detallePP->saveDetallePPProductorIngreso($prestamoPendiente->id_pp, $request->get('id_caja'), $request->get('cant_total'));


                                            //Decrementar detalle pp anterior
                                            $this->detallePP->decrementCantidadDevuelta($detallePPAnterior->id_dpp, $detalleAnterior->cant_total);

                                            // decrementar cant_total detalle anterior
                                            $this->detalle->decrement($detalleAnterior->id_det, $detalleAnterior->cant_total);

                                            //Actualizar proveedor
                                            //Buscar proveeedor
                                            $prov = $this->proveedor->getListaProveedores()[0];
                                            if ($prov != null) {

                                                //Buscar prestamo pp proveedor
                                                $prestamoPProveedor = $this->prestamoPendiente->exists(null, $prov->id_prov);
                                                if ($prestamoPProveedor != null) {
                                                    //Buscar detalle p
                                                    $detallePProveedor = $this->detallePP->existeCajaPrestada($prestamoPProveedor->id_pp, $request->get('id_caja'));
                                                    if ($detallePProveedor != null) {
                                                        // Existe
                                                        //Actualizar cantidad devuelta proveedor
                                                        $this->detallePP->incrementCantidadDevuelta($detallePProveedor->id_dpp, ($cantidadActual - $nuevaCantidad));
                                                    } else {
                                                        //No hay detalle
                                                        //Crear detalle
                                                        $this->detallePP->saveDetallePPProveedorEgreso($prestamoPProveedor->id_pp, $request->get('id_caja'), $request->get('cant_total'));
                                                    }
                                                    //Actualizar detalle prov caja anterior
                                                    //Buscar detalle pp anterior proveeddor
                                                    $detallePPProveedorAnterior = $this->detallePP->existeCajaPrestada($prestamoPProveedor->id_pp, $detallePPAnterior->id_caja);
                                                    if ($detallePPProveedorAnterior != null) {
                                                        //decrementar valor
                                                        $this->detallePP->decrementCantidadDevuelta($detallePPProveedorAnterior->id_dpp, $detalleAnterior->cant_total);
                                                    }
                                                } else {
                                                    //Crear prestamo
                                                    $this->prestamoPendiente->save(null, $prov->id_prov);

                                                    //Obtener prestamo pendiente proveedor
                                                    $nuevoPrestamoPProveedor = $this->prestamoPendiente->exists(null, $prov->id_prov);

                                                    //Crear detalle para la caja
                                                    $this->detallePP->saveDetallePPProveedorEgreso($nuevoPrestamoPProveedor->id_pp, $request->get('id_caja'), $request->get('cant_total'));

                                                }

                                            }

                                        }

                                    }

                                    //Verificar si det está en 0
                                    $this->verificarCantidadDetalleAnterior($detalleAnterior->id_det);

                                    //Verificar cant_prestada = cant_devuelta

                                    $verificarDetallePP = $this->detallePP->existeCajaPrestada($detallePPAnterior->id_pp, $detallePPAnterior->id_caja);

                                    $this->verificarDetallePP($verificarDetallePP);

                                    return response()->json(["mensaje" => "Detalle actualizado con éxito!", 'detalleAnterior' => $detalleAnterior, 'nuevoDetalle' => $detalleNuevo != null ? $this->detalle->getDetalle($detalleNuevo->id_det) : null]);

                                }

                                if ($prestamoPendiente->tipo_pp == 'Cliente') {
                                    //Decrementar stock caja anterior
                                    $this->caja->decrement($det->id_caja, $det->cant_total);
                                    // Incrementar stock nueva caja
                                    $this->caja->increment($request->get('id_caja'), $request->get('cant_total'));


                                    if ($detallePPAnterior != null && $detalleAnterior != null) {
                                        // verificar si ya existe otro detalle pendiente con la nueva caja
                                        $detallePPNuevo = $this->detallePP->existeCajaPrestada($prestamoPendiente->id_pp, $request->get('id_caja'));

                                        //Verificar si existe otro detalle con la nueva caja
                                        $detalleNuevo = $this->detalle->getDetalleByPrestamoAndCaja($pres->id_pres, $request->get('id_caja'));
                                        if ($detallePPNuevo != null && $detalleNuevo != null) {
                                            //Ya existe un detalle con la caja que se editó
                                            // incrementar cant_total nuevo detalle
                                            $this->detalle->increment($detalleNuevo->id_det, $request->get('cant_total'));

                                            // decrementar cant_total detalle caja anterior
                                            $this->detalle->decrement($detalleAnterior->id_det, $detalleAnterior->cant_total);

                                            //incrementar cant_devuelta nuevo detalle pp
                                            $this->detallePP->incrementCantidadDevuelta($detallePPNuevo->id_dpp, $request->get('cant_total'));

                                            //decrementar cant_devuelta detalle pp anterior
                                            $this->detallePP->decrementCantidadDevuelta($detallePPAnterior->id_dpp, $detalleAnterior->cant_total);

                                        } else {
                                            // no exite detalle para la nueva caja
                                            //Crear nuevo detalle

                                            $this->detalle->createNewDetalle($pres->id_pres, $request->get('id_caja'), $request->get('cant_total'));

                                            //Crear nuevo detalle pp -> cant_devuelta
                                            $this->detallePP->saveDetallePPProductorIngreso($prestamoPendiente->id_pp, $request->get('id_caja'), $request->get('cant_total'));


                                            //Decrementar detalle pp anterior
                                            $this->detallePP->decrementCantidadDevuelta($detallePPAnterior->id_dpp, $detalleAnterior->cant_total);

                                            // decrementar cant_total detalle anterior
                                            $this->detalle->decrement($detalleAnterior->id_det, $detalleAnterior->cant_total);


                                        }

                                    }

                                    //Verificar si det está en 0
                                    $this->verificarCantidadDetalleAnterior($detalleAnterior->id_det);

                                    //Verificar cant_prestada = cant_devuelta

                                    $verificarDetallePP = $this->detallePP->existeCajaPrestada($detallePPAnterior->id_pp, $detallePPAnterior->id_caja);

                                    $this->verificarDetallePP($verificarDetallePP);

                                    return response()->json(["mensaje" => "Detalle actualizado con éxito!", 'detalleAnterior' => $detalleAnterior, 'nuevoDetalle' => $detalleNuevo != null ? $this->detalle->getDetalle($detalleNuevo->id_det) : null]);

                                }

                                if ($prestamoPendiente->tipo_pp == 'Proveedor') {

                                    //Decrementar stock caja anterior
                                    $this->caja->decrement($det->id_caja, $det->cant_total);
                                    // Incrementar stock nueva caja
                                    $this->caja->increment($request->get('id_caja'), $request->get('cant_total'));


                                    // Actualizar detalle pp
                                    if ($detallePPAnterior != null && $detalleAnterior != null) {
                                        // verificar si ya existe otro detalle pendiente con la nueva caja
                                        $detallePPNuevo = $this->detallePP->existeCajaPrestada($prestamoPendiente->id_pp, $request->get('id_caja'));

                                        //Verificar si existe otro detalle con la nueva caja
                                        $detalleNuevo = $this->detalle->getDetalleByPrestamoAndCaja($pres->id_pres, $request->get('id_caja'));

                                        if ($detallePPNuevo != null && $detalleNuevo != null) {
                                            //Ya existe un detalle con la caja que se editó
                                            // incrementar cant_total nuevo detalle
                                            $this->detalle->increment($detalleNuevo->id_det, $request->get('cant_total'));

                                            // decrementar cant_total detalle caja anterior
                                            $this->detalle->decrement($detalleAnterior->id_det, $detalleAnterior->cant_total);

                                            //incrementar cant_prestada nuevo detalle pp
                                            $this->detallePP->incrementCantidadPrestada($detallePPNuevo->id_dpp, $request->get('cant_total'));

                                            //decrementar cant_devuelta detalle pp anterior
                                            $this->detallePP->decrementCantidadPrestada($detallePPAnterior->id_dpp, $detalleAnterior->cant_total);

                                        } else {
                                            // no exite detalle para la nueva caja

                                            if ($prestamoPendiente->tipo_pp == 'Proveedor') {
                                                //Crear nuevo detalle
                                                $this->detalle->createNewDetalle($pres->id_pres, $request->get('id_caja'), $request->get('cant_total'));

                                                //Crear nuevo detalle pp -> cant_prestada
                                                $this->detallePP->saveDetallePPProveedorIngreso($prestamoPendiente->id_pp, $request->get('id_caja'), $request->get('cant_total'));
                                            }

                                            if ($prestamoPendiente->tipo_pp == 'Cliente') {
                                                //Crear nuevo detalle
                                                $this->detalle->createNewDetalle($pres->id_pres, $request->get('id_caja'), $request->get('cant_total'));

                                                //Crear nuevo detalle pp -> cant_devuelta
                                                $this->detallePP->saveDetallePPProductorIngreso($prestamoPendiente->id_pp, $request->get('id_caja'), $request->get('cant_total'));

                                            }

                                            // decrementar cant_total detalle anterior
                                            $this->detallePP->decrementCantidadPrestada($detallePPAnterior->id_dpp, $detalleAnterior->cant_total);


                                        }

                                    }

                                    //Eliminar detalle anterior
                                    $this->detalle->deleteDetalle($detalleAnterior->id_det);

                                    $this->verificarCantidadDetalleAnterior($detalleAnterior->id_det);

                                    //Verificar cant_prestada = cant_devuelta
                                    $verificarDetallePP = $this->detallePP->existeCajaPrestada($detallePPAnterior->id_pp, $detallePPAnterior->id_caja);
                                    $this->verificarDetallePP($verificarDetallePP);

                                    return response()->json(["mensaje" => "Detalle actualizado con éxito!", 'detalleAnterior' => $detalleAnterior, 'nuevoDetalle' => $detalleNuevo != null ? $this->detalle->getDetalle($detalleNuevo->id_det) : null]);

                                }
                            }

                            if ($pres->tipo == 'Egreso') {
                                // Incrementar stock caja anterior
                                $this->caja->increment($det->id_caja, $det->cant_total);
                                // Decrementar stock nueva caja
                                $this->caja->decrement($request->get('id_caja'), $request->get('cant_total'));
                                // Actualizar detalle (caja nueva y cantidad)

                                // obtener el detalle con caja anterior
                                $detalleAnterior = $this->detalle->getDetalle($request->get('id_det'));
                                $detallePPAnterior = $this->detallePP->existeCajaPrestada($prestamoPendiente->id_pp, $detalleAnterior->id_caja);


                                // Actualizar detalle pp
                                if ($detallePPAnterior != null && $detalleAnterior != null) {
                                    // verificar si ya existe otro detalle pendiente con la nueva caja
                                    $detallePPNuevo = $this->detallePP->existeCajaPrestada($prestamoPendiente->id_pp, $request->get('id_caja'));

                                    //Verificar si existe otro detalle con la nueva caja
                                    $detalleNuevo = $this->detalle->getDetalleByPrestamoAndCaja($pres->id_pres, $request->get('id_caja'));
                                    if ($detallePPNuevo != null && $detalleNuevo != null) {
                                        //Ya existe un detalle con la caja que se editó
                                        // incrementar cant_total detalle anterior
                                        if ($pres->id_prov != null) {
                                            //Egreso proveedor
                                            //Decrement cant_total detalle anterior
                                            $this->detalle->decrement($detalleAnterior->id_det, $cantidadActual);

                                            //Incrementar cantidad nuevo detalle
                                            $this->detalle->increment($detalleNuevo->id_det, $nuevaCantidad);

                                            //Actualizar detalle pp
                                            //Detalle anterior
                                            $this->detallePP->decrementCantidadDevuelta($detallePPAnterior->id_dpp, $detalleAnterior->cant_total);
                                            //nuevo detalle
                                            $this->detallePP->incrementCantidadDevuelta($detallePPNuevo->id_dpp, $nuevaCantidad);

                                        } else {
                                            //Egreso productor o cliente
                                            //Decrement cant_total detalle anterior
                                            $this->detalle->decrement($detalleAnterior->id_det, $cantidadActual);

                                            //Incrementar cantidad nuevo detalle
                                            $this->detalle->increment($detalleNuevo->id_det, $nuevaCantidad);

                                            //Actualizar detalle pp
                                            //Detalle anterior
                                            $this->detallePP->decrementCantidadPrestada($detallePPAnterior->id_dpp, $detalleAnterior->cant_total);
                                            //nuevo detalle
                                            $this->detallePP->incrementCantidadPrestada($detallePPNuevo->id_dpp, $nuevaCantidad);
                                        }

                                    } else {
                                        // no exite detalle para la nueva caja

                                        if ($prestamoPendiente->tipo_pp == 'Proveedor') {
                                            //Crear nuevo detalle
                                            $this->detalle->createNewDetalle($pres->id_pres, $request->get('id_caja'), $request->get('cant_total'));

                                            //Crear nuevo detalle pp -> cant_devuelta
                                            $this->detallePP->saveDetallePPProveedorEgreso($prestamoPendiente->id_pp, $request->get('id_caja'), $request->get('cant_total'));

                                            // Decrementar cant_devuelta pp
                                            $this->detallePP->decrementCantidadDevuelta($detallePPAnterior->id_dpp, $detalleAnterior->cant_total);
                                        }

                                        if ($prestamoPendiente->tipo_pp == 'Cliente' || $prestamoPendiente->tipo_pp == 'Productor') {
                                            //Crear nuevo detalle
                                            $this->detalle->createNewDetalle($pres->id_pres, $request->get('id_caja'), $request->get('cant_total'));

                                            //Crear nuevo detalle pp -> cant_devuelta
                                            $this->detallePP->saveDetallePPProductorEgreso($prestamoPendiente->id_pp, $request->get('id_caja'), $request->get('cant_total'));

                                            //decrementar cant_pres pp
                                            $this->detallePP->decrementCantidadPrestada($detallePPAnterior->id_dpp, $detalleAnterior->cant_total);

                                        }

                                        // decrementar cant_total detalle anterior
                                        $this->detalle->decrement($detalleAnterior->id_det, $detalleAnterior->cant_total);

                                    }

                                }

                                $this->verificarCantidadDetalleAnterior($detalleAnterior->id_det);

                                //Verificar cant_prestada = cant_devuelta
                                $verificarDetallePP = $this->detallePP->existeCajaPrestada($detallePPAnterior->id_pp, $detallePPAnterior->id_caja);
                                $this->verificarDetallePP($verificarDetallePP);

                                return response()->json(["mensaje" => "Detalle actualizado con éxito!", 'detalleAnterior' => $detalleAnterior, 'nuevoDetalle' => $detalleNuevo != null ? $this->detalle->getDetalle($detalleNuevo->id_det) : null]);
                            }

                        }

                    }

                }
            }
        }
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param int $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        //Obtener detalle
        $det = $this->detalle->getDetalle($id);
        if ($det != null) {
            //Verificar tipo de prestamo
            $pres = $this->prestamo->getPrestamo($det->id_pres);
            if ($pres != null) {
                //Verificar si el proveedor/productor ya existe en prestamo pendiente
                $prestamoPendiente = $this->prestamoPendiente->exists($pres->id_prod, $pres->id_prov);

                if ($prestamoPendiente != null) {
                    if ($pres->tipo == 'Ingreso') {
                        //Verificar si prodcutor es de valle frio
                        $prod = $this->productor->findProductor($pres->id_prod);

                        if ($prestamoPendiente->tipo_pp == 'Productor') {
                            // Buscar detalle pp -> decrementar cantidad devuelta
                            $detallePP = $this->detallePP->existeCajaPrestada($prestamoPendiente->id_pp, $det->id_caja);
                            if ($detallePP != null) {
                                if ($prod->valle_frio) {
                                    // Es de valle
                                    // decrementar detalle caja
                                    $this->detallePP->decrementCantidadDevuelta($detallePP->id_dpp, $det->cant_total);

                                    //decrementar cant devuelta detalle proveedor
                                    //Buscar proveedor
                                    $prov = $this->proveedor->getListaProveedores()[0];
                                    if ($prov != null) {
                                        // Buscar prestamo proveedor
                                        $prestamoPendienteProveedor = $this->prestamoPendiente->exists(null, $prov->id_prov);
                                        if ($prestamoPendienteProveedor != null) {
                                            // Buscar detalle caja eliminada
                                            $detallePPProveedor = $this->detallePP->existeCajaPrestada($prestamoPendienteProveedor->id_pp, $det->id_caja);
                                            if ($detallePPProveedor != null) {
                                                //decrementar detalle cant-devuelta
                                                $this->detallePP->decrementCantidadDevuelta($detallePPProveedor->id_dpp, $det->cant_total);
                                            }
                                        }
                                    }
                                } else {
                                    //No es de valle
                                    $this->caja->decrement($det->id_caja, $det->cant_total);
                                    //Decrementar cantidad devuelta detalle p
                                    $this->detallePP->decrementCantidadDevuelta($detallePP->id_dpp, $det->cant_total);
                                }
                            }

                        } else {
                            // Decrementar stock caja
                            $this->caja->decrement($det->id_caja, $det->cant_total);

                            // Buscar detalle pp -> decrementar cantidad devuelta
                            $detallePP = $this->detallePP->existeCajaPrestada($prestamoPendiente->id_pp, $det->id_caja);
                            if ($prestamoPendiente->tipo_pp == 'Proveedor') {
                                // Decrementar cantidad prestada
                                $this->detallePP->decrementCantidadPrestada($detallePP->id_dpp, $det->cant_total);
                            }
                            if ($prestamoPendiente->tipo_pp == 'Cliente') {
                                // Decrementar cantidad devuelta
                                $this->detallePP->decrementCantidadDevuelta($detallePP->id_dpp, $det->cant_total);
                            }
                        }

                        //Verificar si cant_prestada == cant_devuelta
                        $prestamoPendiente = $this->prestamoPendiente->exists($pres->id_prod, $pres->id_prov);
                        if ($prestamoPendiente) {
                            //Buscar sus detalles
                            $detallesPP = $this->detallePP->getDetallesPrestamoPendiente($prestamoPendiente->id_pp);

                            //Verificar detalle -> cant_prest === cant_devuelta
                            $this->verificarDetallesPrestamoPendiente($detallesPP, $prestamoPendiente->id_pp);

                        }
                        // Cambiar estado a detalle
                        return $this->detalle->deleteDetalle($det->id_det);
                    }

                    if ($pres->tipo == 'Egreso') {
                        // Incrementar stock caja
                        $this->caja->increment($det->id_caja, $det->cant_total);

                        // Buscar detalle pp -> decrementar cantidad devuelta
                        $detallePP = $this->detallePP->existeCajaPrestada($prestamoPendiente->id_pp, $det->id_caja);
                        if ($prestamoPendiente->tipo_pp == 'Productor' || $prestamoPendiente->tipo_pp == 'Cliente') {
                            //Decrementar cant prestada
                            $this->detallePP->decrementCantidadPrestada($detallePP->id_dpp, $det->cant_total);
                        }

                        if ($prestamoPendiente->tipo_pp == 'Proveedor') {
                            //decrementar cantidad devuelta
                            $this->detallePP->decrementCantidadDevuelta($detallePP->id_dpp, $det->cant_total);
                        }

                        //Verificar cant pres = cant _devuelta
                        //Verificar si cant_prestada == cant_devuelta
                        $prestamoPendiente = $this->prestamoPendiente->exists($pres->id_prod, $pres->id_prov);
                        if ($prestamoPendiente) {
                            //Buscar sus detalles
                            $detallesPP = $this->detallePP->getDetallesPrestamoPendiente($prestamoPendiente->id_pp);

                            //Verificar detalle -> cant_prest === cant_devuelta
                            $this->verificarDetallesPrestamoPendiente($detallesPP, $prestamoPendiente->id_pp);

                        }

                        // Cambiar estado a detalle
                        return $this->detalle->deleteDetalle($det->id_det);
                    }
                }

            }
        }
    }

    public function mostrarDetalle($id)
    {
        $pres = $this->prestamo->getDatosPrestamo($id);
        if ($pres != null) {
            //Get details
            $detalles = $this->detalle->getDetalleByPrestamo($id);
            // dd($detalles);
            $cajas = $this->caja->getDatosCajas();
            return view('modulos.Prestamo.Detalle.index', compact('pres', 'detalles', 'cajas'));
        }

    }

    public function verificarDetallesPrestamoPendiente($detallesPP, $idPP)
    {
        if (!$detallesPP->isEmpty()) {
            for ($i = 0; $i < sizeof($detallesPP); $i++) {
                if ($detallesPP[$i]->cant_prest === $detallesPP[$i]->cant_devuelta) {
                    //Eliminar detalle
                    $this->detallePP->deleteDetallePP($detallesPP[$i]->id_dpp);
                }
            }
        } else {
            //Eliminar prestamo pendiente
            $this->prestamoPendiente->delete($idPP);
        }
    }

    public function verificarDetallePP($verificarDetallePP)
    {
        if ($verificarDetallePP != null) {
            $detallepp = $this->detallePP->getDetallePP($verificarDetallePP->id_dpp);
            if ($detallepp != null) {
                if ($detallepp->cant_prest === $detallepp->cant_devuelta) {
                    //Eliminar detalle PP
                    $this->detallePP->deleteDetallePP($detallepp->id_dpp);

                    //Verificar detalles
                    $detallesPP = $this->detallePP->getDetallesPrestamoPendiente($detallepp->id_pp);
                    $this->verificarDetallesPrestamoPendiente($detallesPP, $detallepp->id_pp);
                }
            }
        }
    }

    public function verificarCantidadDetalleAnterior($idDetalleAnterior)
    {
        $detalleAnterior = $this->detalle->getDetalle($idDetalleAnterior);
        if ($detalleAnterior->cant_total === 0) {
            //Eliminar
            $this->detalle->deleteDetalle($detalleAnterior->id_det);
        }
    }

    public function verificarPrestamoPendienteExistente($idProd, $idProv)
    {
        $prestamoPendiente = $this->prestamoPendiente->exists($idProd, $idProv);
        if ($prestamoPendiente == null) {
            $this->prestamoPendiente->save($idProd, $idProv);
            return $this->prestamoPendiente->exists($idProd, $idProv);
        }
        return $prestamoPendiente;
    }

    public function verificarDetallePPExistente($idPP, $idCaja)
    {
        $detallePP = $this->detallePP->existeCajaPrestada($idPP, $idCaja);
        if ($detallePP == null) {
            $this->detallePP->saveDetallePPProductorIngreso($idPP, $idCaja, 0);
            return $this->detallePP->existeCajaPrestada($idPP, $idCaja);
        }
        return $detallePP;
    }
}
