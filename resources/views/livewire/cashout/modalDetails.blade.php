<div wire:ignore.self id="modal-details" class="modal fade" tabindex="-1" role="dialog" >
    <div class="modal-dialog modal-lg" role="document" >
        <div class="modal-content">
            <div class="modal-header bg-dark">
                <h5 class="modal-title text-white">
                    <b>Detalle de Venta</b>
                </h5>
                <button class="close" data-dismiss="modal" type="button" aria-label="Close">
                    <span class="text-white">&times;</span>
                </button>
            </div>

            <div class="modal-body">
                <div class="table-responsive">
                            <table class="table table-bordered table-striped mt-1">
                                <thead class="text-white" style="background: #620408">
                                    <tr>
                                        <th class="table-th text-white text-center">CLIENTE</th>
                                        <th class="table-th text-white text-center">PRODUCTO</th>
                                        <th class="table-th text-white text-center">CANTIDAD</th>
                                        <th class="table-th text-white text-center">PRECIO</th>
                                        <th class="table-th text-white text-center">IMPORTE</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @if($total <= 0)
                                        <tr>
                                            <td colspan="4" class="text-center"><h6> No hay registros</h6></td>
                                        </tr>
                                    @endif

                                    @foreach($details as $detail)
                                        <tr>
                                            <td class="text-center">{{$detail->customer}}</td>
                                            <td class="text-center">{{$detail->product}}</td>
                                            <td class="text-center">{{$detail->quantity}}</td>
                                            <td class="text-center">{{number_format($detail->price, 0)}} Gs.</td>
                                            <td class="text-center">{{number_format($detail->quantity * $detail->price, 0)}}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                                <tfoot>
                                    <td class="text-right"><h5 class="text-info">TOTALES:</h5></td>
                                    <td class="text-center">
                                        @if($details)
                                            <h5 class="text-info">{{$details->sum('quantity')}}</h5>
                                        @endif
                                    </td>
                                    @if($details)
                                    @php $myTotal =0;  @endphp
                                        @foreach($details as $detail)
                                            @php $myTotal += $detail->quantity * $detail->price; @endphp
                                        @endforeach
                                        <td class="text-center"><h5 class="text-info">{{number_format($myTotal, 0)}} Gs.</h5></td>
                                    @endif
                                </tfoot>
                            </table>
                            <div class="modal-footer">        
                                <button type="button" class="btn btn-dark close-btn text-info" data-dismiss="modal">CERRAR</button>
                            </div>
                        </div>
                        
                </div>
                
        </div>
        
    </div>
</div>