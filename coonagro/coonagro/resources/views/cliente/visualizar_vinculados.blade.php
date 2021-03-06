@extends('layouts.form-principal',  ['tag' => '1'])

@section('css') ../css/carregamento.css @endsection

@section('conteudo')
    <?php date_default_timezone_set('America/Sao_Paulo');  ?>
    
    <div class="container panel-form">
        @if(isset($_GET['msg']))
            <br>
            <div class="alert alert-warning" role="alert">
                {{$_GET['msg']}}
            </div>
        @endif

        <h4 style="padding: 30px; color: #63950A"> <b>PEDIDOS VINCULADOS À TRANSPORTADORAS</b> </h4>
        
        <form action="{{route('cliente.pedidos_vinculados_filtrar')}}">
            <input type ="hidden" name="_token" value="{{{ csrf_token() }}}">
            <div class="row">
                <div class="col-4">
                    <label>Transportadora:</label>
                    <select id="transportadora"
                            name="transportadora"
                            class="form-control"
                        >
                        <option value="0">TODOS</option>
                        @foreach($transportadoras as $t)
                            <option value="{{$t->CODIGO}}">{{$t->NOME}}</option>
                        @endforeach
                    </select>
                </div>
                
                <div class="col-4">
                    <label>Nº Pedido:</label>
                    <input type="text" name="num_pedido" class="form-control">
                </div>
            </div>

            <div class="row">
                <div class="col-4">
                    <label>Data:</label>
                    <input type="date" name="data" class="form-control">
                </div>
                
                <div class="col-4">
                    <label>Produto:</label>
                    <select id="produto"
                            name="produto"
                            class="form-control"
                        >
                        <option value="0">TODOS</option>
                        @foreach($produtos as $p)
                            <option value="{{$p->CODIGO}}">{{$p->DESCRICAO}}</option>
                        @endforeach
                    </select>
                </div>

                <div class="col-4">
                    <button class="btn btn-primary btn-block"><i class="fas fa-search"></i></button>
                </div>
            </div>
        </form>
        <br>
        <div class="table-responsive">
            <table id="table" class="table table-striped" style="width: 100%">
                <thead>
                    <th>Nº Pedido</th>
                    <th>Produto</th>
                    <th>Transportadora</th>
                    <th>Data</th>
                    <th>Quantidade Limite</th>
                    <th>Desvincular</th>
                    <th>Editar</th>
                </thead>
                <tbody>
                    @if(count($pedidos) == 0)
                        <tr>
                            <td colspan="4">Nenhum pedido em aberto</td>
                        </tr>
                    @else
                        @foreach($pedidos as $pedido)
                            <tr>
                                <td>{{$pedido->NUM_PEDIDO}}</td>
                                <td>{{$pedido->PRODUTO->DESCRICAO}}</td>
                                <td>{{$pedido->TRANSPORTADORA->NOME}}</td>
                                @if($pedido->DATA != null)
                                    <td>{{date_format(date_create($pedido->DATA), 'd/m/Y')}}</td>
                                @else
                                    <td></td>
                                @endif
                                <td>{{$pedido->COTA}}</td>
                                <td class="agendamento">
                                    <a onclick="desvincular({{$pedido->CODIGO}})" target="_blank">
                                        <i class="fas fa-minus-circle" title="Desvincular pedido" style="cursor: pointer; color: #545b62"></i>
                                    </a>
                                </td>
                                @if($pedido->DATA != NULL && $pedido->COTA != NULL)
                                    <td>
                                        <a href="{{route('cliente.editar', ['pedido' => $pedido->CODIGO])}}">
                                            <i class="fas fa-edit" title="Editar" style="cursor: pointer; color: #545b62"></i>
                                        </a>
                                    </td>
                                @else
                                    <td></td>
                                @endif
                            </tr>
                        @endforeach
                    @endif
                </tbody>
            </table>
        </div>
        <a id="voltar" href="{{route('cliente.vincular_pedidos')}}">
            <button class="btn btn-success btn-lg btn-block back">
                <i class="fas fa-arrow-left"></i> Voltar
            </button>
        </a>
    </div>
    @section('js')../js/visualizar_vinculados.js @endsection
@stop

