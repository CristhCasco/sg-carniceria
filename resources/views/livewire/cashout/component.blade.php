<div class="row sales layout-top-spacing">
    <div class="col-sm-12">
        <div class="widget widget-chart-one">
            <div class="widget-heading">
                <h4 class="cart-title text-center">
                    <b>Corte de Caja</b>
                </h4>
            </div>

            <div class="widget-content">
                <div class="row">
                    <div class="col-sm-12 col-md-3">
                        <div class="form-group">
                            <label>Usuario</label>
                            <select wire:model="userId" class="form-control">
                                <option value="0">Elegir</option>
                                @foreach($users as $user)
                                    <option value="{{ $user->id }}">{{ $user->name }}</option>
                                @endforeach
                            </select>
                            @error('userId') <span class="text-danger">{{ $message }}</span>@enderror
                        </div>
                    </div>

                    <div class="col-sm-12 col-md-3">
                        <div class="form-group">
                            <label>Fecha Inicio</label>
                            <input type="date" wire:model.lazy="fromDate" class="form-control">
                            @error('fromDate') <span class="text-danger">{{ $message }}</span>@enderror
                        </div>
                    </div>
                    
                    <div class="col-sm-12 col-md-3">
                        <div class="form-group">
                            <label>Fecha Final</label>
                            <input type="date" wire:model.lazy="toDate" class="form-control">
                            @error('toDate') <span class="text-danger">{{ $message }}</span>@enderror
                        </div>
                    </div>

                    <div class="col-sm-12 col-md-3 align-self-center d-flex justify-content-around">
                        @if($userId > 0 && $fromDate !=null && $toDate != null)
                            <button wire:click.prevent="Consult" type="button"
                            class="btn btn-dark">Consultar</button>
                        @endif
                        
                        <!--
                        @if($total > 0)
                            <button wire:click.prevent="Print()" type="button"
                            class="btn btn-dark">Imprimir</button>
                        @endif
                        -->
                    </div>
                </div>
            </div>

            <div class="row mt-5">
                <div class="col-sm-12 col-md-4 mbmobile">
                    <div class="connect-sorting bg-dark">
                        <h5 class="text-white">Ventas Totales: {{number_format($total, 0)}} Gs.</h5>
                        <h5 class="text-white">Articulos: {{$items}}</h5>
                    </div>
                </div>

                <div class="col-sm-12 col-md-8">
                    <div class="table-responsive">
                        <table class="table table-bordered table-striped mt-1">
                            <thead class="text-white" style="background: #620408">
                                <tr>
                                    <th class="table-th text-white text-center">FOLIO</th>
                                    <th class="table-th text-white text-center">TOTAL</th>
                                    <th class="table-th text-white text-center">ITEMS</th>
                                    <th class="table-th text-white text-center"></th>
                                </tr>
                            </thead>
                            <tbody>
                                @if($total <= 0)
                                    <tr>
                                        <td colspan="4" class="text-center"><h6> No hay registros</h6></td>
                                    </tr>
                                @endif

                                @foreach($sales as $sale)
                                    <tr>
                                        <td class="text-center">{{$sale->id}}</td>
                                        <td class="text-center">{{number_format($sale->total, 0)}} Gs.</td>
                                        <td class="text-center">{{$sale->items}}</td>
                                        <td class="text-center">
                                            <button wire:click.prevent="viewDetails({{$sale}})" class="btn btn-dark btn-sm">
                                                <i class="fas fa-list"></i>
                                            </button>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
    @include('livewire.cashout.modalDetails')
</div>

<script>
    document.addEventListener('DOMContentLoaded', function(){
        window.livewire.on('show-modal', msg => {
            $('#modal-details').modal('show');
        });
    });
</script>