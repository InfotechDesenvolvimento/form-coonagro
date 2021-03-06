<?php


namespace App\Http\Controllers\Administrador;


use App\Mail\EnviaEmail;
use Mail;
use App\Agendamento;
use App\AgendamentoAlteracao;
use App\Codigos;
use App\Http\Controllers\Controller;
use App\Http\Controllers\CotaClienteController;
use App\Http\Controllers\MotoristaController;
use App\Http\Controllers\PedidoTransporteController;
use App\Http\Controllers\TransportadoraController;
use App\Http\Controllers\VeiculoController;
use App\PedidosVinculadosTransportadora;
use App\Motorista;
use App\Produto;
use App\PedidoTransporte;
use App\TipoEmbalagem;
use App\TipoVeiculo;
use App\Transportadora;
use App\Veiculo;
use App\Cliente;
use App\CotaCliente;
use App\NotaFiscal;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use SimpleSoftwareIO\QrCode\Facades\QrCode;

class AgendamentoController extends Controller
{
    public function __construct(){
        $this->middleware('auth:admin');
    }

    public function index(){
        $tipos = TipoVeiculo::orderBy('TIPO_VEICULO')->get();
        $embalagens = TipoEmbalagem::orderBy('TIPO_EMBALAGEM')->get();

        return view('administrador.agendamento', compact(['tipos', 'embalagens']));
    }

    public function show($codigo){
        return Agendamento::where('CODIGO', $codigo)->with(['produto', 'embalagem', 'tipoVeiculo'])->first();
    }

    public function imprimir($cod_agendamento){
        $agendamento = $this->show($cod_agendamento);

        $qrcode = QrCode::size(150)->generate($agendamento->CODIGO);
        
        set_time_limit(300);

        return \PDF::loadView('administrador.imprimir', ['agendamento' => $agendamento, 'qrcode' => $qrcode])
            ->stream('agendamento-coonagro.pdf');
    }
    
    public function getCliente($cod_cliente){
        $cliente = Cliente::find($cod_cliente);
        return $cliente->NOME;
    }

    public function totalAgendadoClientes() {
        $agendamentos = DB::select("SELECT SUM(QUANTIDADE) AS TOTAL, clientes.NOME AS CLIENTE FROM agendamentos
                                LEFT OUTER JOIN clientes on (clientes.CODIGO = agendamentos.COD_CLIENTE) WHERE COD_STATUS_AGENDAMENTO <= 3
                                GROUP BY clientes.NOME");
        $total_agendado = Agendamento::where('COD_STATUS_AGENDAMENTO', '<=', 3)->sum('QUANTIDADE');
        return view('administrador.total_agendado_clientes', compact('agendamentos', 'total_agendado'));
    }

    public function filtrarTotalAgendadoClientes(Request $request) {

        if($request->get('data_agendamento') != '') {
                $data = $request->get('data_agendamento');
                $agendamentos = DB::select("SELECT SUM(QUANTIDADE) AS TOTAL, clientes.NOME AS CLIENTE FROM agendamentos
                                        LEFT OUTER JOIN clientes on (clientes.CODIGO = agendamentos.COD_CLIENTE) WHERE COD_STATUS_AGENDAMENTO <= 3 and DATA_AGENDAMENTO = '$data'
                                        GROUP BY clientes.NOME");
                $total_agendado = Agendamento::where('COD_STATUS_AGENDAMENTO', '<=', 3)->where('DATA_AGENDAMENTO', '=', $request->get('data_agendamento'))->sum('QUANTIDADE');
                return view('administrador.total_agendado_clientes', compact('agendamentos', 'total_agendado'));
        } else {
                return redirect()->route('administrador.total_agendado_clientes');
        }
    }

    public function totalAgendadoTransportadoras() {
        $agendamentos = DB::select("SELECT SUM(QUANTIDADE) AS TOTAL, TRANSPORTADORA from agendamentos where COD_STATUS_AGENDAMENTO <= 3 group by TRANSPORTADORA");
        $total_agendado = Agendamento::where('COD_STATUS_AGENDAMENTO', '<=', 3)->sum('QUANTIDADE');
        return view('administrador.total_agendado_transportadoras', compact('agendamentos', 'total_agendado'));
    }

    public function filtrarTotalAgendadoTransportadoras(Request $request) {

        if($request->get('data_agendamento') != '') {
                $data = $request->get('data_agendamento');
                $agendamentos = DB::select("SELECT SUM(QUANTIDADE) AS TOTAL, TRANSPORTADORA from agendamentos where COD_STATUS_AGENDAMENTO <= 3 and DATA_AGENDAMENTO = '$data' group by TRANSPORTADORA");
                $total_agendado = Agendamento::where('COD_STATUS_AGENDAMENTO', '<=', 3)->where('DATA_AGENDAMENTO', '=', $request->get('data_agendamento'))->sum('QUANTIDADE');
                return view('administrador.total_agendado_transportadoras', compact('agendamentos', 'total_agendado'));
        } else {
                return redirect()->route('administrador.total_agendado_transportadoras');
        }
    }

    public function visualizarVinculos() {
        $pedidos = PedidosVinculadosTransportadora::with('produto')->with('cliente')->with('transportadora')->with('pedido_transporte')->get();
        $produtos = DB::select('SELECT produtos.DESCRICAO, produtos.CODIGO FROM pedidos_vinculados_transportadora, produtos WHERE pedidos_vinculados_transportadora.COD_PRODUTO = produtos.CODIGO GROUP BY produtos.DESCRICAO');
        $transportadoras = DB::select('SELECT transportadoras.NOME, transportadoras.CODIGO FROM pedidos_vinculados_transportadora, transportadoras WHERE pedidos_vinculados_transportadora.COD_TRANSPORTADORA = transportadoras.CODIGO GROUP BY transportadoras.NOME');
        $clientes = DB::select('SELECT clientes.NOME, clientes.CODIGO FROM pedidos_vinculados_transportadora, clientes WHERE pedidos_vinculados_transportadora.COD_CLIENTE = clientes.CODIGO GROUP BY clientes.NOME');
        //return json_encode($pedidos);
        return view('administrador.visualizar_vinculos', compact('pedidos', 'transportadoras', 'produtos', 'clientes'));
    }

    public function filtrarVinculos(Request $request) {

        $pedidos = PedidosVinculadosTransportadora::when($request->get('num_pedido') != "", function ($query) use ($request) {
                                                $query->where('NUM_PEDIDO', $request->get('num_pedido'));
                                        })->when($request->get('produto') != "0", function ($query) use ($request){
                                                $query->where('COD_PRODUTO', $request->get('produto'));
                                        })->when($request->get('data') != "", function ($query) use ($request){
                                                $query->where('DATA', $request->get('data'));
                                        })->when($request->get('transportadora') != "0", function ($query) use ($request){
                                                $query->where('COD_TRANSPORTADORA', $request->get('transportadora'));
                                        })->when($request->get('cliente') != "0", function ($query) use ($request) {
                                                $query->where('COD_CLIENTE', $request->get('cliente'));
                                        })->with('transportadora')->with('produto')->with('cliente')->orderBy('CODIGO')->get();

        $produtos = DB::select('SELECT produtos.DESCRICAO, produtos.CODIGO FROM pedidos_vinculados_transportadora, produtos WHERE pedidos_vinculados_transportadora.COD_PRODUTO = produtos.CODIGO GROUP BY produtos.DESCRICAO');
        $transportadoras = DB::select('SELECT transportadoras.NOME, transportadoras.CODIGO FROM pedidos_vinculados_transportadora, transportadoras WHERE pedidos_vinculados_transportadora.COD_TRANSPORTADORA = transportadoras.CODIGO GROUP BY transportadoras.NOME');
        $clientes = DB::select('SELECT clientes.NOME, clientes.CODIGO FROM pedidos_vinculados_transportadora, clientes WHERE pedidos_vinculados_transportadora.COD_CLIENTE = clientes.CODIGO GROUP BY clientes.NOME');

        return view('administrador.visualizar_vinculos', compact('pedidos', 'produtos', 'transportadoras', 'clientes'));
    }

    public function filter(Request $request) {
        if($request->get('data_especifica') == '') {
                $agendamentos = Agendamento::when($request->get('num_agendamento') != "", function ($query) use ($request) {
                                                $query->where('CODIGO', $request->get('num_agendamento'));
                                        })->when($request->get('status') != "0", function ($query) use ($request){
                                                $query->where('COD_STATUS_AGENDAMENTO', $request->get('status'));
                                        })->when($request->get('produto') != "0", function ($query) use ($request){
                                                $query->where('COD_PRODUTO', $request->get('produto'));
                                        })->when($request->get('data_inicial') != "", function ($query) use ($request){
                                                $query->where('DATA_AGENDAMENTO', '>=', $request->get('data_inicial'));
                                        })->when($request->get('data_final') != "", function ($query) use ($request){
                                                $query->where('DATA_AGENDAMENTO', '<=', $request->get('data_final'));
                                        })->when($request->get('num_pedido') != "", function ($query) use ($request){
                                                $query->where('NUM_PEDIDO', $request->get('num_pedido'));
                                        })->when($request->get('transportadora') != "", function ($query) use ($request){
                                                $query->where('TRANSPORTADORA', 'LIKE', '%' . $request->get('transportadora') .'%');
                                        })->when($request->get('placa_veiculo') != "", function ($query) use ($request){
                                                $query->where('PLACA_VEICULO', 'LIKE', '%' . $request->get('placa_veiculo') . '%');
                                        })->when($request->get('placa_carreta') != "", function ($query) use ($request){
                                                $query->where('PLACA_CARRETA1', 'LIKE', '%' . $request->get('placa_carreta') . '%');
                                        })->when($request->get('cliente') != "0", function ($query) use ($request) {
                                                $query->where('COD_CLIENTE', $request->get('cliente'));
                                        })->with('status')->with('produto')->with('cliente')->orderBy('CODIGO')->get();
        } else {
                $agendamentos = Agendamento::when($request->get('num_agendamento') != "", function ($query) use ($request) {
                                                $query->where('CODIGO', $request->get('num_agendamento'));
                                        })->when($request->get('status') != "0", function ($query) use ($request){
                                                $query->where('COD_STATUS_AGENDAMENTO', $request->get('status'));
                                        })->when($request->get('produto') != "0", function ($query) use ($request){
                                                $query->where('COD_PRODUTO', $request->get('produto'));
                                        })->when($request->get('data_especifica') != "", function ($query) use ($request){
                                                $query->where('DATA_AGENDAMENTO', $request->get('data_especifica'));
                                        })->when($request->get('num_pedido') != "", function ($query) use ($request){
                                                $query->where('NUM_PEDIDO', $request->get('num_pedido'));
                                        })->when($request->get('transportadora') != "", function ($query) use ($request){
                                                $query->where('TRANSPORTADORA', 'LIKE', '%' . $request->get('transportadora') .'%');
                                        })->when($request->get('placa_veiculo') != "", function ($query) use ($request){
                                                $query->where('PLACA_VEICULO', 'LIKE', '%' . $request->get('placa_veiculo') . '%');
                                        })->when($request->get('placa_carreta') != "", function ($query) use ($request){
                                                $query->where('PLACA_CARRETA1', 'LIKE', '%' . $request->get('placa_carreta') . '%');
                                        })->when($request->get('cliente') != "0", function ($query) use ($request) {
                                                $query->where('COD_CLIENTE', $request->get('cliente'));
                                        })->with('status')->with('produto')->with('cliente')->orderBy('CODIGO')->get();
        }

        return response()->json($agendamentos);
    }

    public function desvincular($cod_pedido) {
        $vinculo = PedidosVinculadosTransportadora::find($cod_pedido);

        if($vinculo != null) {
            $vinculo->delete();
            $msg = "Vínculo removido!";
        } else {
            $msg = "Vínculo não encontrado!";
        }

        $pedidos = PedidosVinculadosTransportadora::with('transportadora')->with('pedido_transporte')->with('produto')->with('cliente')->get();

        return redirect()->route('administrador.vinculos', compact('pedidos', 'msg'));
    }

    public function verDetalhe($cod_agendamento) {
        $agendamento = Agendamento::where('CODIGO', $cod_agendamento)->with('tipoVeiculo')->with('embalagem')->first();

        if($agendamento != null) {
                return view('administrador.agendamento_detalhes', compact('agendamento'));
        }
    }

    public function aprovarAgendamento($cod_agendamento) {
        $agendamento = Agendamento::where('CODIGO', $cod_agendamento)->first();
        $agendamento->COD_STATUS_AGENDAMENTO = 2;
        $agendamento->COD_FUNCIONARIO_APROVA = Auth::user()->getAuthIdentifier();

        date_default_timezone_set("America/Sao_Paulo");

        $agendamento->DATA_ALTERACAO = date('Y-m-d');
        $agendamento->HORA_ALTERACAO = now();  
        $agendamento->save();
        
        $cliente = Cliente::where('CODIGO', $agendamento->COD_CLIENTE)->first();
        $cota_cliente = CotaCliente::where('COD_CLIENTE', $cliente->CODIGO)->where('DATA', $agendamento->DATA_AGENDAMENTO)->first();
        if($cota_cliente == null) {
                $cota_cliente = new CotaCliente();
                $cota_cliente->COD_CLIENTE = $cliente->CODIGO;
                $cota_cliente->DATA = $agendamento->DATA_AGENDAMENTO;
                $cota_cliente->COTA_DIARIA = $cliente->COTA_MAXIMA_DIARIA;
                $cota_cliente->TOTAL_AGENDADO = 0;
                $cota_cliente->TOTAL_MOVIMENTADO = 0;
                $cota_cliente->SALDO_LIVRE = $cliente->COTA_MAXIMA_DIARIA;
                $cota_cliente->PERCENTUAL_LIVRE = 100;
                $cota_cliente->PERCENTUAL_MOVIMENTADO = 0;
                $cota_cliente->save();
        }
        $cota_cliente->SALDO_LIVRE = $cota_cliente->SALDO_LIVRE - $agendamento->QUANTIDADE;
        $calc = $cota_cliente->SALDO_LIVRE - $agendamento->QUANTIDADE;
        $cota_cliente->PERCENTUAL_LIVRE = (100 * $calc) / $cota_cliente->COTA_DIARIA;
        $cota_cliente->save();

        $pedido_transporte = PedidoTransporte::where('NUM_PEDIDO', $agendamento->NUM_PEDIDO)->first();
        $pedido_transporte->SALDO_RESTANTE = $pedido_transporte->SALDO_RESTANTE - $agendamento->QUANTIDADE;
        $pedido_transporte->save();
        
        $nota_fiscal = new NotaFiscal;
        $nota_fiscal->DATA_CADASTRO = date('Y-m-d');
        $nota_fiscal->DATA_ALTERACAO = date('Y-m-d');
        $nota_fiscal->COD_AGENDAMENTO = $agendamento->CODIGO;
        $nota_fiscal->COD_ORIGEM = 10;
        $nota_fiscal->NUM_PEDIDO = $agendamento->NUM_PEDIDO;
        $nota_fiscal->TRANSP_XNOME = $agendamento->TRANSPORTADORA;
        $nota_fiscal->TRANSP_CNPJ_CPF = $agendamento->CNPJ_TRANSPORTADORA;
        $nota_fiscal->TRANSP_PLACA_VEICULO = $agendamento->PLACA_VEICULO;
        $nota_fiscal->PLACA_CARRETA1 = $agendamento->PLACA_CARRETA1;
        $nota_fiscal->RENAVAM = $agendamento->RENAVAM_VEICULO;
        $nota_fiscal->COD_PRODUTO = $agendamento->COD_PRODUTO;
        $nota_fiscal->QUANTIDADE = $agendamento->QUANTIDADE;
        $nota_fiscal->COD_TIPO_EMBALAGEM = $agendamento->COD_EMBALAGEM;
        $nota_fiscal->NOME_CONDUTOR = $agendamento->CONDUTOR;
        $nota_fiscal->CPF_CONDUTOR = $agendamento->CPF_CONDUTOR;
        $nota_fiscal->DATA_AGENDAMENTO = $agendamento->DATA_AGENDAMENTO;
        $nota_fiscal->COD_OPERACAO = 1;
        $nota_fiscal->COD_CLIENTE = $agendamento->COD_CLIENTE;
        
        $nota_fiscal->save();

        $msg = 'Agendamento aprovado!';
        return view('administrador.agendamento_detalhes', compact('agendamento', 'msg'));
    }

    public function recusarAgendamento($cod_agendamento) {
        $agendamento = Agendamento::where('CODIGO', $cod_agendamento)->first();
        $agendamento->COD_STATUS_AGENDAMENTO = 4;
        $agendamento->save();

        $msg = 'Agendamento não aprovado!';
        return view('administrador.agendamento_detalhes', compact('agendamento', 'msg'));
    }

    public function cancelarAgendamento($cod_agendamento) {
        $agendamento = Agendamento::where('CODIGO', $cod_agendamento)->first();
        $agendamento->COD_STATUS_AGENDAMENTO = 5;
        $agendamento->save();

        $pedido_transporte = PedidoTransporte::where('NUM_PEDIDO', $agendamento->NUM_PEDIDO)->first();
        $pedido_transporte->SALDO_RESTANTE = $pedido_transporte->SALDO_RESTANTE + $agendamento->QUANTIDADE;
        $pedido_transporte->TOTAL_AGENDADO = $pedido_transporte->TOTAL_AGENDADO - $agendamento->QUANTIDADE;
        $pedido_transporte->save();

        $cota_cliente = CotaCliente::where('COD_CLIENTE', $agendamento->COD_CLIENTE)->where('DATA', $agendamento->DATA_AGENDAMENTO)->first();
        $cota_cliente->TOTAL_AGENDADO = $cota_cliente->TOTAL_AGENDADO - $agendamento->QUANTIDADE;
        $cota_cliente->save();

        $msg = 'Agendamento cancelado!';
        return view('administrador.agendamento_detalhes', compact('agendamento', 'msg'));
    }

    public function visualizarPedidos() {
        $pedidos = PedidoTransporte::with('cliente')->with('produto')->get();
        //return json_encode($pedidos);
        $produtos = DB::select('SELECT produtos.DESCRICAO, produtos.CODIGO FROM pedido_transporte, produtos WHERE pedido_transporte.COD_PRODUTO = produtos.CODIGO GROUP BY produtos.DESCRICAO');
        $clientes = DB::select('SELECT clientes.NOME, clientes.CODIGO FROM pedido_transporte, clientes WHERE pedido_transporte.COD_CLIENTE = clientes.CODIGO GROUP BY clientes.NOME');

        return view('administrador.pedidos', compact('pedidos', 'clientes', 'produtos'));
    }

    public function filtrarAlteracoes(Request $request) {
        $alteracoes = AgendamentoAlteracao::when($request->get('num_pedido') != "", function ($query) use ($request) {
                                                $query->where('NUM_PEDIDO', $request->get('num_pedido'));
                                        })->when($request->get('produto') != "0", function ($query) use ($request){
                                                $query->where('COD_PRODUTO', $request->get('produto'));
                                        })->when($request->get('cliente') != "0", function ($query) use ($request) {
                                                $query->where('COD_CLIENTE', $request->get('cliente'));
                                        })->when($request->get('data') != "", function ($query) use ($request) {
                                                $query->where('DATA_AGENDAMENTO', $request->get('data'));
                                        })->where('STATUS_ALTERACAO', 'PENDENTE')->with('produto')->with('cliente')->orderBy('CODIGO')->get();

        $produtos = DB::select('SELECT produtos.DESCRICAO, produtos.CODIGO FROM agendamentos_alteracao, produtos WHERE agendamentos_alteracao.COD_PRODUTO = produtos.CODIGO GROUP BY produtos.DESCRICAO');
        $clientes = DB::select('SELECT clientes.NOME, clientes.CODIGO FROM agendamentos_alteracao, clientes WHERE agendamentos_alteracao.COD_CLIENTE = clientes.CODIGO GROUP BY clientes.NOME');
        $transportadoras = DB::select('SELECT TRANSPORTADORA, COD_TRANSPORTADORA FROM agendamentos_alteracao GROUP BY TRANSPORTADORA');

        return view('administrador.visualizar_alteracoes', compact('alteracoes', 'clientes', 'produtos', 'transportadoras'));
    }

    public function selecionarAlteracao($cod_alteracao) {
        $alteracao = AgendamentoAlteracao::where('CODIGO', $cod_alteracao)->with('tipoVeiculo')->with('embalagem')->first();
        $agendamento = Agendamento::where('CODIGO', $alteracao->COD_AGENDAMENTO)->with('tipoVeiculo')->with('embalagem')->first();

        return view('administrador.detalhes_alteracao', compact('alteracao', 'agendamento'));
    }

    public function visualizarAlteracoes() {
        $alteracoes = AgendamentoAlteracao::where('STATUS_ALTERACAO', 'PENDENTE')->with('produto')->with('cliente')->get();
        

        $produtos = DB::select('SELECT produtos.DESCRICAO, produtos.CODIGO FROM agendamentos_alteracao, produtos WHERE agendamentos_alteracao.COD_PRODUTO = produtos.CODIGO GROUP BY produtos.DESCRICAO');
        $clientes = DB::select('SELECT clientes.NOME, clientes.CODIGO FROM agendamentos_alteracao, clientes WHERE agendamentos_alteracao.COD_CLIENTE = clientes.CODIGO GROUP BY clientes.NOME');
        $transportadoras = DB::select('SELECT TRANSPORTADORA, COD_TRANSPORTADORA FROM agendamentos_alteracao GROUP BY TRANSPORTADORA');

        return view('administrador.visualizar_alteracoes', compact('alteracoes', 'clientes', 'produtos', 'transportadoras'));
    }

    public function salvarAlteracoes($cod_alteracao) {
        $alteracao = AgendamentoAlteracao::find($cod_alteracao);
        $agendamento = Agendamento::find($alteracao->COD_AGENDAMENTO);

        $agendamento->NUM_PEDIDO = $alteracao->NUM_PEDIDO;
        $agendamento->DATA_AGENDAMENTO = $alteracao->DATA_AGENDAMENTO;
        $agendamento->TRANSPORTADORA = $alteracao->TRANSPORTADORA;
        $agendamento->CNPJ_TRANSPORTADORA = $alteracao->CNPJ_TRANSPORTADORA;
        $agendamento->PLACA_VEICULO = $alteracao->PLACA_VEICULO;
        $agendamento->PLACA_CARRETA1 = $alteracao->PLACA_CARRETA1;
        $agendamento->PLACA_CARRETA2 = $alteracao->PLACA_CARRETA2;
        $agendamento->PLACA_CARRETA3 = $alteracao->PLACA_CARRETA3;
        $agendamento->RENAVAM_VEICULO = $alteracao->RENAVAM_VEICULO;
        $agendamento->COD_TIPO_VEICULO = $alteracao->COD_TIPO_VEICULO;
        $agendamento->TARA_VEICULO = $alteracao->TARA_VEICULO;
        $agendamento->CONDUTOR = $alteracao->CONDUTOR;
        $agendamento->CPF_CONDUTOR = $alteracao->CPF_CONDUTOR;
        $agendamento->COD_PRODUTO = $alteracao->COD_PRODUTO;
        $agendamento->QUANTIDADE = $alteracao->QUANTIDADE;
        $agendamento->COD_EMBALAGEM = $alteracao->COD_EMBALAGEM;
        $agendamento->OBS = $alteracao->OBS;
        $agendamento->COD_TRANSPORTADORA = $alteracao->COD_TRANSPORTADORA;
        $agendamento->DATA_CADASTRO = $alteracao->DATA_CADASTRO;
        $agendamento->HORA_CADASTRO = $alteracao->HORA_CADASTRO;
        $agendamento->DATA_ALTERACAO = $alteracao->DATA_ALTERACAO;
        $agendamento->HORA_ALTERACAO = $alteracao->HORA_ALTERACAO;
        $agendamento->COD_STATUS_AGENDAMENTO = $alteracao->COD_STATUS_AGENDAMENTO;
        $agendamento->COD_CLIENTE = $alteracao->COD_CLIENTE;
        if($agendamento->save()) {
                $alteracao->STATUS_ALTERACAO = 'APROVADO';
                $alteracao->COD_RESPONSAVEL_ALTERACAO = Auth::user()->getAuthIdentifier();
                $alteracao->save();
        }

        return redirect()->route('administrador.alteracoes');
    }

    public function cancelarAlteracoes($cod_alteracao) {
        $alteracao = AgendamentoAlteracao::where('CODIGO', $cod_alteracao)->first();
        $alteracao->STATUS_ALTERACAO = 'CANCELADO';
        $alteracao->save();

        $msg = 'Alteração recusada!';
        return redirect()->route('administrador.alteracoes', compact('msg'));
    }

    public function filtrarPedidos(Request $request) {
        $pedidos = PedidoTransporte::when($request->get('num_pedido') != "", function ($query) use ($request) {
                                        $query->where('NUM_PEDIDO', $request->get('num_pedido'));
                                })->when($request->get('produto') != "0", function ($query) use ($request){
                                        $query->where('COD_PRODUTO', $request->get('produto'));
                                })->when($request->get('cliente') != "0", function ($query) use ($request) {
                                        $query->where('COD_CLIENTE', $request->get('cliente'));
                                })->with('produto')->with('cliente')->orderBy('CODIGO')->get();
        
        $produtos = DB::select('SELECT produtos.DESCRICAO, produtos.CODIGO FROM pedido_transporte, produtos WHERE pedido_transporte.COD_PRODUTO = produtos.CODIGO GROUP BY produtos.DESCRICAO');
        $clientes = DB::select('SELECT clientes.NOME, clientes.CODIGO FROM pedido_transporte, clientes WHERE pedido_transporte.COD_CLIENTE = clientes.CODIGO GROUP BY clientes.NOME');

        return view('administrador.pedidos', compact('pedidos', 'clientes', 'produtos'));
    }

    public function visualizarCotas() {
        $clientes = Cliente::get();

        return view('administrador.visualizar_cotas', compact('clientes'));
    }
}
