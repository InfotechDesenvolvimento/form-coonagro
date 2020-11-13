<?php

namespace App\Http\Controllers;

use App\PedidoTransporte;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PedidoTransporteController extends Controller
{
    public function update($num_pedido, $quantidade){

        $pedido = PedidoTransporte::where('NUM_PEDIDO', $num_pedido)->first();

        if($pedido != null){
            $pedido->TOTAL_AGENDADO = $pedido->TOTAL_AGENDADO + $quantidade;

            $pedido->save();
        }
    }
}
