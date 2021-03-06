<?php


namespace App\Http\Controllers\Transportadora;


use App\Mail\EnviaEmail;
use App\Mail\EnviaEmail_alteracao;
use Mail;
use App\Agendamento;
use App\Codigos;
use App\Http\Controllers\Controller;
use App\Http\Controllers\CotaClienteController;
use App\Http\Controllers\MotoristaController;
use App\Http\Controllers\PedidoTransporteController;
use App\Http\Controllers\TransportadoraController;
use App\Http\Controllers\VeiculoController;
use App\Motorista;
use App\PedidoTransporte;
use App\TipoEmbalagem;
use App\TipoVeiculo;
use App\Transportadora;
use App\AgendamentoAlteracao;
use App\StatusAgendamento;
use App\Veiculo;
use App\Produto;
use App\Cliente;
use App\PedidosVinculadosTransportadora;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use SimpleSoftwareIO\QrCode\Facades\QrCode;

class AgendamentoController extends Controller
{
    public function __construct(){
        $this->middleware('auth:transportadora');
    }

    public function index(){
        $tipos = TipoVeiculo::orderBy('TIPO_VEICULO')->get();
        $embalagens = TipoEmbalagem::orderBy('TIPO_EMBALAGEM')->get();

        return view('transportadora.agendamento', compact(['tipos', 'embalagens']));
    }

    public function validarDados(Request $request){
        $confirmacao = $request->all();

        $date = date_create($confirmacao['data_agendamento']);
        $confirmacao["data_agendamento_formatado"] = date_format($date,"d/m/Y");
        $confirmacao['placa_carreta'] = strtoupper($confirmacao['placa_carreta']);
        $confirmacao['placa_cavalo'] = strtoupper($confirmacao['placa_cavalo']);
        $confirmacao['placa_carreta2'] = strtoupper($confirmacao['placa_carreta2']);
        $confirmacao['placa_carreta3'] = strtoupper($confirmacao['placa_carreta3']);

        $tipo_veiculo = json_decode($confirmacao['tipo_veiculo'][0]);

        $tipo_veiculo = TipoVeiculo::find($tipo_veiculo->id);
        $confirmacao['tipo_veiculo_nome'] = $tipo_veiculo->TIPO_VEICULO;

        $tipo_embalagem = TipoEmbalagem::find($confirmacao['tipo_embalagem']);
        $confirmacao['tipo_embalagem_nome'] = $tipo_embalagem->TIPO_EMBALAGEM;

        $date = date_create($confirmacao['validade_cnh']);
        $confirmacao['validade_cnh_formatado'] = date_format($date, "d/m/Y");

        session()->put('agendamento', json_encode($request->input()));

        return view('transportadora.confirmacao-carregamento', compact(['confirmacao']));
    }

    public function finalizar(){

        if(session()->has('agendamento')){
            $dados = json_decode(session()->get('agendamento'));
            
            $objVeiculo = new VeiculoController();
            $veiculo = $objVeiculo->getVeiculo($dados->placa_cavalo);
            $veiculo = $veiculo->getData();

                if(count(get_object_vars($veiculo)) == 0){
                    $tipo_veiculo = json_decode($dados->tipo_veiculo[0]);

                    $veiculo = new Veiculo();

                    $veiculo->PLACA = strtoupper($dados->placa_cavalo);
                    $veiculo->PLACA_CARRETA = strtoupper($dados->placa_carreta);
                    if($dados->placa_carreta2 != null) {
                        $veiculo->PLACA_CARRETA2 = $dados->placa_carreta2;
                    }
                    if($dados->placa_carreta3 != null) {
                        $veiculo->PLACA_CARRETA3 = $dados->placa_carreta3;
                    }
                    $veiculo->COD_TIPO_VEICULO = $tipo_veiculo->id;
                    $veiculo->TARA = $this->formataValor($dados->tara);
                    if($dados->renavam != null) {
                        $veiculo->RENAVAM = $dados->renavam;
                    }

                    $objVeiculo->insert($veiculo);
                }

            $objMotorista = new MotoristaController();
            $motorista = $objMotorista->show($dados->cpf_motorista);
            $motorista = $motorista->getData();

                if(count(get_object_vars($motorista)) == 0){
                    $motorista = new Motorista();

                    $motorista->CPF_CNPJ = $dados->cpf_motorista;
                    $motorista->NOME = strtoupper($dados->nome_motorista);
                    if($dados->cnh != null) {
                        $motorista->CNH = $dados->cnh;
                    }
                    if($dados->validade_cnh != null) {
                        $motorista->DATA_VALIDADE_CNH = $dados->validade_cnh;
                    }

                    $objMotorista->insert($motorista);
                }

            $agendamento = new Agendamento();

            $agendamento->NUM_PEDIDO = $dados->num_pedido;
            $agendamento->DATA_AGENDAMENTO = $dados->data_agendamento;
            $agendamento->TRANSPORTADORA = Auth::user()->NOME;
            $agendamento->CNPJ_TRANSPORTADORA = Auth::user()->CPF_CNPJ;
            $agendamento->PLACA_VEICULO = strtoupper($dados->placa_cavalo);
            $agendamento->PLACA_CARRETA1 = strtoupper($dados->placa_carreta);
            if($dados->placa_carreta2 != null) {
                $agendamento->PLACA_CARRETA2 = strtoupper($dados->placa_carreta2);
            }
            if($dados->placa_carreta3 != null) {
                $agendamento->PLACA_CARRETA3 = strtoupper($dados->placa_carreta3);
            }
            if($dados->renavam != null) {
                $agendamento->RENAVAM_VEICULO = $dados->renavam;
            }

            $tipo_veiculo = json_decode($dados->tipo_veiculo[0]);

            $agendamento->COD_TIPO_VEICULO = $tipo_veiculo->id;
            $agendamento->TARA_VEICULO = $this->formataValor($dados->tara);
            $agendamento->CONDUTOR = strtoupper($dados->nome_motorista);
            $agendamento->CPF_CONDUTOR = $dados->cpf_motorista;
            $agendamento->COD_PRODUTO = $dados->cod_produto;
            $agendamento->QUANTIDADE = $this->formataValor($dados->quantidade);
            $agendamento->COD_EMBALAGEM = $dados->tipo_embalagem;
            $agendamento->OBS = $dados->observacao;

            $objPedidoTransporte = new PedidoTransporteController();
            $pedido = $objPedidoTransporte->getObjPedido($dados->num_pedido);
            $agendamento->COD_CLIENTE = $pedido->COD_CLIENTE;

            return $this->insert($agendamento);
        } else {
            return redirect()->route('transportadora.operacao');
        }
    }

    public function insert(Agendamento $agendamento){
        $cliente = Cliente::find($agendamento->COD_CLIENTE);
        
        date_default_timezone_set("America/Sao_Paulo");

        $agendamento->DATA_CADASTRO = date("Y-m-d");
        $agendamento->DATA_ALTERACAO = date("Y-m-d");
        $agendamento->HORA_CADASTRO = now();
        $agendamento->HORA_ALTERACAO = now();  
        $agendamento->COD_STATUS_AGENDAMENTO = 1;
        $agendamento->COD_TRANSPORTADORA = Auth::user()->getAuthIdentifier();

        $email = 0;
        
        if($cliente->EMAIL != null && Auth::user()->EMAIL != null) {
            if(!filter_var(Auth::user()->EMAIL, FILTER_VALIDATE_EMAIL)) {
                $erro = 'Agendamento não pôde ser concluído, e-mail da TRANSPORTADORA inválido! Favor alterar para um endereço válido!';
                session()->forget('agendamento');
                return redirect()->route('transportadora.carregamento.falha', $erro);
            }

            elseif(!filter_var($cliente->EMAIL, FILTER_VALIDATE_EMAIL)) {
                $erro = 'Agendamento não pôde ser concluído, e-mail do CLIENTE inválido! Favor alterar para um endereço válido!';
                session()->forget('agendamento');
                return redirect()->route('transportadora.carregamento.falha', $erro);
            }
            else {
                $email = 1;
            }
        }

        if($agendamento->save())
        {
            $objPedidoTransporte = new PedidoTransporteController();
            $objPedidoTransporte->update($agendamento->NUM_PEDIDO, $agendamento->QUANTIDADE);

            $pedido = $objPedidoTransporte->getObjPedido($agendamento->NUM_PEDIDO);
            
            $objCotaCliente = new CotaClienteController();
            $objCotaCliente->update($pedido->COD_CLIENTE, $agendamento->DATA_AGENDAMENTO, $agendamento->QUANTIDADE);

            $objCotaTransp = PedidosVinculadosTransportadora::where('COD_CLIENTE', $pedido->COD_CLIENTE)->where('COD_TRANSPORTADORA', $agendamento->COD_TRANSPORTADORA)->where('NUM_PEDIDO', $agendamento->NUM_PEDIDO)->where('COD_PRODUTO', $agendamento->COD_PRODUTO)->where('DATA', $agendamento->DATA_AGENDAMENTO)->first();
            if($objCotaTransp != null) {
                $cota = $objCotaTransp->COTA - $agendamento->QUANTIDADE;
                $objCotaTransp->COTA = $cota;
                $objCotaTransp->save();
            }
        }

        $cod_agendamento = $agendamento->CODIGO;
        
        if($email == 1) {
            $data = $agendamento->ToJson();
            $data = json_decode($data);
            Mail::to($cliente->EMAIL)->send(new EnviaEmail($data));
            Mail::to(Auth::user()->EMAIL)->send(new EnviaEmail($data));
        }

        session()->forget('agendamento');
        return redirect()->route('transportadora.carregamento.sucesso', $cod_agendamento);
    }

    public function falha($erro) {
        return view('transportadora.operacao_falha', compact('erro'));
    }

    public function sucesso($cod_agendamento) {
        return view('transportadora.mensagem-sucesso', compact('cod_agendamento'));
    }

    public function show($codigo){
        return Agendamento::where('CODIGO', $codigo)->with(['produto', 'embalagem', 'tipoVeiculo', 'cliente'])->first();
    }

    public function editarAgendamento($cod_agendamento) {
        $agendamento = $this->show($cod_agendamento);
        $objMotorista = new MotoristaController();
        $motorista = $objMotorista->show($agendamento->CPF_CONDUTOR);
        $motorista = $motorista->getData();

        $tipos = TipoVeiculo::orderBy('TIPO_VEICULO')->get();
        $embalagens = TipoEmbalagem::orderBy('TIPO_EMBALAGEM')->get();

        return view('transportadora.editar_agendamento', compact(['agendamento', 'tipos', 'embalagens']));
    }

    public function alterarAgendamento(Request $request) {
        $alteracao = $request->all();

        $agendamento = Agendamento::find($alteracao['cod_agendamento']);
        if($agendamento->COD_STATUS_AGENDAMENTO == 1) {
            $agendamento->PLACA_VEICULO = strtoupper($alteracao['placa_cavalo']);
            $agendamento->PLACA_CARRETA1 = strtoupper($alteracao['placa_carreta']);
            $agendamento->PLACA_CARRETA2 = strtoupper($alteracao['placa_carreta2']);
            $agendamento->PLACA_CARRETA3 = strtoupper($alteracao['placa_carreta3']);
            $tipo_veiculo = json_decode($alteracao['tipo_veiculo']);
            $agendamento->COD_TIPO_VEICULO = $tipo_veiculo->id;
            $agendamento->TRANSPORTADORA = Auth::user()->NOME;
            $agendamento->CNPJ_TRANSPORTADORA = Auth::user()->CPF_CNPJ;
            $agendamento->TARA_VEICULO = $this->formataValor($alteracao['tara']);
            $agendamento->CONDUTOR = $alteracao['nome_motorista'];
            $agendamento->CPF_CONDUTOR = $alteracao['cpf_motorista'];
            $agendamento->COD_EMBALAGEM = $alteracao['tipo_embalagem'];
            $agendamento->OBS = $alteracao['observacao'];
            date_default_timezone_set("America/Sao_Paulo");
            $agendamento->DATA_ALTERACAO = date("Y-m-d");
            $agendamento->HORA_ALTERACAO = now();
            $agendamento->COD_STATUS_AGENDAMENTO = 1;
            $agendamento->COD_TRANSPORTADORA = Auth::user()->getAuthIdentifier();

            $objVeiculo = new VeiculoController();
            $veiculo = $objVeiculo->getVeiculo($alteracao['placa_cavalo']);
            $veiculo = $veiculo->getData();

            if(count(get_object_vars($veiculo)) == 0){
                $tipo_veiculo = json_decode($alteracao['tipo_veiculo']);

                $veiculo = new Veiculo();

                $veiculo->PLACA = strtoupper($alteracao['placa_cavalo']);
                $veiculo->PLACA_CARRETA = strtoupper($alteracao['placa_carreta']);
                if($alteracao['placa_carreta2'] != null) {
                    $veiculo->PLACA_CARRETA2 = $alteracao['placa_carreta2'];
                }
                if($alteracao['placa_carreta3'] != null) {
                    $veiculo->PLACA_CARRETA3 = $alteracao['placa_carreta3'];
                }
                $veiculo->COD_TIPO_VEICULO = $tipo_veiculo->id;
                $veiculo->TARA = $this->formataValor($alteracao->tara);
                $objVeiculo->insert($veiculo);
            }

            $objMotorista = new MotoristaController();
            $motorista = $objMotorista->show($alteracao['cpf_motorista']);
            $motorista = $motorista->getData();

            if(count(get_object_vars($motorista)) == 0){
                $motorista = new Motorista();

                $motorista->CPF_CNPJ = $alteracao['cpf_motorista'];
                $motorista->NOME = strtoupper($alteracao['nome_motorista']);
                $objMotorista->insert($motorista);
            }
            
            $cliente = Cliente::find($agendamento->COD_CLIENTE);

            if($agendamento->save()) {
                if($cliente->EMAIL != null && Auth::user()->EMAIL != null) {
                    if(!filter_var(Auth::user()->EMAIL, FILTER_VALIDATE_EMAIL)) {
                        $erro = 'Alteração concluída! E-mail da TRANSPORTADORA inválido! Favor alterar para um endereço válido!';
                        session()->forget('agendamento');
                        return redirect()->route('transportadora.carregamento.falha', $erro);
                    }
        
                    elseif(!filter_var($cliente->EMAIL, FILTER_VALIDATE_EMAIL)) {
                        $erro = 'Alteração concluída! E-mail do CLIENTE inválido! Favor alterar para um endereço válido!';
                        session()->forget('agendamento');
                        return redirect()->route('transportadora.carregamento.falha', $erro);
                    }
                    else {
                        $data = $agendamento->ToJson();
                        $data = json_decode($data);
                        Mail::to($cliente->EMAIL)->send(new EnviaEmail($data));
                        Mail::to(Auth::user()->EMAIL)->send(new EnviaEmail($data));
                    }
                }
                return redirect()->route('transportadora.carregamento.sucesso', $agendamento->CODIGO);
            }
            $erro = 'Agendamento não pôde ser editado!';
            return redirect()->route('transportadora.carregamento.falha', $erro);
        } elseif($agendamento->COD_STATUS_AGENDAMENTO == 2) {
            $agendamento_alteracao = new AgendamentoAlteracao();
            $agendamento_alteracao->COD_AGENDAMENTO = $agendamento->CODIGO;
            $agendamento_alteracao->PLACA_VEICULO = strtoupper($alteracao['placa_cavalo']);
            $agendamento_alteracao->PLACA_CARRETA1 = strtoupper($alteracao['placa_carreta']);
            $agendamento_alteracao->PLACA_CARRETA2 = strtoupper($alteracao['placa_carreta2']);
            $agendamento_alteracao->PLACA_CARRETA3 = strtoupper($alteracao['placa_carreta3']);
            $tipo_veiculo = json_decode($alteracao['tipo_veiculo']);
            $agendamento_alteracao->COD_TIPO_VEICULO = $tipo_veiculo->id;
            $agendamento_alteracao->TRANSPORTADORA = Auth::user()->NOME;
            $agendamento_alteracao->CNPJ_TRANSPORTADORA = Auth::user()->CPF_CNPJ;
            $agendamento_alteracao->TARA_VEICULO = $this->formataValor($alteracao['tara']);
            $agendamento_alteracao->CONDUTOR = $alteracao['nome_motorista'];
            $agendamento_alteracao->CPF_CONDUTOR = $alteracao['cpf_motorista'];
            $agendamento_alteracao->COD_EMBALAGEM = $alteracao['tipo_embalagem'];
            $agendamento_alteracao->OBS = $alteracao['observacao'];
            date_default_timezone_set("America/Sao_Paulo");
            $agendamento_alteracao->DATA_CADASTRO = $agendamento->DATA_CADASTRO;
            $agendamento_alteracao->HORA_CADASTRO = $agendamento->HORA_CADASTRO;
            $agendamento_alteracao->DATA_ALTERACAO = date("Y-m-d");
            $agendamento_alteracao->HORA_ALTERACAO = now();
            $agendamento_alteracao->COD_STATUS_AGENDAMENTO = 1;
            $agendamento_alteracao->COD_TRANSPORTADORA = Auth::user()->getAuthIdentifier();
            $agendamento_alteracao->NUM_PEDIDO = $alteracao['num_pedido'];
            $agendamento_alteracao->DATA_AGENDAMENTO = $alteracao['data_agendamento'];
            $agendamento_alteracao->QUANTIDADE = $alteracao['quantidade'];
            $agendamento_alteracao->COD_STATUS_AGENDAMENTO = $agendamento->COD_STATUS_AGENDAMENTO;
            $agendamento_alteracao->COD_CLIENTE = $alteracao['cod_cliente'];
            $agendamento_alteracao->COD_PRODUTO = $alteracao['cod_produto'];
            $agendamento_alteracao->STATUS_ALTERACAO = 'PENDENTE';

            $objVeiculo = new VeiculoController();
            $veiculo = $objVeiculo->getVeiculo($alteracao['placa_cavalo']);
            $veiculo = $veiculo->getData();

            if(count(get_object_vars($veiculo)) == 0){
                $tipo_veiculo = json_decode($alteracao['tipo_veiculo']);

                $veiculo = new Veiculo();

                $veiculo->PLACA = strtoupper($alteracao['placa_cavalo']);
                $veiculo->PLACA_CARRETA = strtoupper($alteracao['placa_carreta']);
                if($alteracao['placa_carreta2'] != null) {
                    $veiculo->PLACA_CARRETA2 = $alteracao['placa_carreta2'];
                }
                if($alteracao['placa_carreta3'] != null) {
                    $veiculo->PLACA_CARRETA3 = $alteracao['placa_carreta3'];
                }
                $veiculo->COD_TIPO_VEICULO = $tipo_veiculo->id;
                $veiculo->TARA = $this->formataValor($alteracao->tara);
                $objVeiculo->insert($veiculo);
            }

            $objMotorista = new MotoristaController();
            $motorista = $objMotorista->show($alteracao['cpf_motorista']);
            $motorista = $motorista->getData();

            if(count(get_object_vars($motorista)) == 0){
                $motorista = new Motorista();

                $motorista->CPF_CNPJ = $alteracao['cpf_motorista'];
                $motorista->NOME = strtoupper($alteracao['nome_motorista']);
                $objMotorista->insert($motorista);
            }
            
            $cliente = Cliente::find($agendamento->COD_CLIENTE);

            if($agendamento_alteracao->save()) {
                if($cliente->EMAIL != null && Auth::user()->EMAIL != null) {
                    if(!filter_var(Auth::user()->EMAIL, FILTER_VALIDATE_EMAIL)) {
                        $erro = 'Alteração solicitada, e-mail da TRANSPORTADORA inválido! Favor alterar para um endereço válido!';
                        session()->forget('agendamento');
                        return redirect()->route('transportadora.carregamento.falha', $erro);
                    }
        
                    elseif(!filter_var($cliente->EMAIL, FILTER_VALIDATE_EMAIL)) {
                        $erro = 'Alteração solicitada, e-mail do CLIENTE inválido! Favor alterar para um endereço válido!';
                        session()->forget('agendamento');
                        return redirect()->route('transportadora.carregamento.falha', $erro);
                    }
                    else {
                        $data = $agendamento_alteracao->ToJson();
                        $data = json_decode($data);
                        Mail::to($cliente->EMAIL)->send(new EnviaEmail_alteracao($data));
                        Mail::to(Auth::user()->EMAIL)->send(new EnviaEmail_alteracao($data));
                    }
                }
                return redirect()->route('transportadora.carregamento.sucesso', $agendamento->CODIGO);
            }
            $erro = 'Agendamento não pôde ser editado!';
            return redirect()->route('transportadora.carregamento.falha', $erro);
        }
    }

    public function excluirAgendamento($cod_agendamento) {
        $agendamento = Agendamento::find($cod_agendamento);

        $objCC = new CotaClienteController();
        $objCC->update($agendamento->COD_CLIENTE, $agendamento->DATA, ((-1)*$agendamento->QUANTIDADE)); 

        $objPedidoTransporte = new PedidoTransporteController();
        $objPedidoTransporte->update($agendamento->NUM_PEDIDO, $agendamento->QUANTIDADE);

        $objCotaTransp = PedidosVinculadosTransportadora::where('COD_CLIENTE', $pedido->COD_CLIENTE)->where('COD_TRANSPORTADORA', $agendamento->COD_TRANSPORTADORA)->where('NUM_PEDIDO', $agendamento->NUM_PEDIDO)->where('COD_PRODUTO', $agendamento->COD_PRODUTO)->where('DATA', $agendamento->DATA_AGENDAMENTO)->first();
        if($objCotaTransp != null) {
            $cota = $objCotaTransp->COTA + $agendamento->QUANTIDADE;
            $objCotaTransp->COTA = $cota;
            $objCotaTransp->save();
        }

        $agendamento->delete();
        $msg = 'Agendamento excluído!';
        return redirect()->route('transportadora.home', compact('msg'));
    }

    public function imprimir($cod_agendamento){
        $agendamento = $this->show($cod_agendamento);

        $qrcode = QrCode::size(150)->generate($agendamento->CODIGO);
        
        set_time_limit(300);

        return \PDF::loadView('transportadora.imprimir', ['agendamento' => $agendamento, 'qrcode' => $qrcode])
            ->stream('agendamento-coonagro.pdf');

    }

    public function filter(Request $request){

        $cod_transportadora = Auth::user()->getAuthIdentifier();

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
                                    })->where('COD_TRANSPORTADORA', $cod_transportadora)->with('status')->with('produto')->orderBy('CODIGO')->get();
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
                                    })->where('COD_TRANSPORTADORA', $cod_transportadora)->with('status')->with('produto')->orderBy('CODIGO')->get();
        }
        return response()->json($agendamentos);
    }

    public function totalAgendado() {
        $cod_transportadora = Auth::user()->getAuthIdentifier();

        //$agendamentos = Agendamento::where('COD_CLIENTE', $cod_cliente)->groupBy('TRANSPORTADORA')->get();
        $agendamentos = DB::select("SELECT SUM(QUANTIDADE) AS TOTAL, clientes.NOME AS CLIENTE FROM agendamentos
                                    LEFT OUTER JOIN clientes on (clientes.CODIGO = agendamentos.COD_CLIENTE)
                                    WHERE agendamentos.COD_TRANSPORTADORA = $cod_transportadora
                                    AND agendamentos.COD_STATUS_AGENDAMENTO <= 3
                                    GROUP BY clientes.NOME");
        $total_agendado = Agendamento::where('COD_TRANSPORTADORA', $cod_transportadora)->where('COD_STATUS_AGENDAMENTO', '<=', '3')->sum('QUANTIDADE');

        return view('transportadora.total_agendado', compact('agendamentos', 'total_agendado'));
    }

    public function filtrarTotalAgendado(Request $request) {
        $cod_transportadora = Auth::user()->getAuthIdentifier();

        //$agendamentos = Agendamento::where('COD_CLIENTE', $cod_cliente)->groupBy('TRANSPORTADORA')->get();
        if($request->get('data_agendamento') != '') {
            $data = $request->get('data_agendamento');
            $agendamentos = DB::select("SELECT SUM(QUANTIDADE) AS TOTAL, clientes.NOME AS CLIENTE FROM agendamentos
                                        LEFT OUTER JOIN clientes on (clientes.CODIGO = agendamentos.COD_CLIENTE)
                                        WHERE agendamentos.COD_TRANSPORTADORA = $cod_transportadora
                                        AND agendamentos.COD_STATUS_AGENDAMENTO <= 3
                                        AND agendamentos.DATA_AGENDAMENTO = '$data'
                                        GROUP BY clientes.NOME");
            $total_agendado = Agendamento::where('COD_TRANSPORTADORA', $cod_transportadora)->where('COD_STATUS_AGENDAMENTO', '<=', '3')->where('DATA_AGENDAMENTO', '=', $request->get('data_agendamento'))->sum('QUANTIDADE');
            return view('transportadora.total_agendado', compact('agendamentos', 'total_agendado'));
        } else {
            return redirect()->route('transportadora.total_agendado');
        }
    }

    public function formataValor($valor){
        $valor = str_replace('.', '', $valor);
        $valor = str_replace(',', '.', $valor);
        return $valor;
    }

    public function visualizarPedidosVinculados(){
        $cod_transportadora = Auth::user()->getAuthIdentifier();

        $pedidos = PedidosVinculadosTransportadora::where('COD_TRANSPORTADORA', $cod_transportadora)
        ->with('pedido_transporte')->with('produto')->with('cliente')->get();


        $produtos = DB::select('SELECT produtos.DESCRICAO, produtos.CODIGO FROM agendamentos, produtos WHERE agendamentos.COD_PRODUTO = produtos.CODIGO AND agendamentos.COD_TRANSPORTADORA = '.$cod_transportadora.' GROUP BY produtos.DESCRICAO');
        $clientes = DB::select('SELECT clientes.NOME, clientes.CODIGO FROM agendamentos, clientes WHERE agendamentos.COD_CLIENTE = clientes.CODIGO AND agendamentos.COD_TRANSPORTADORA = '.$cod_transportadora.' GROUP BY clientes.NOME');

        return view('transportadora.pedidos_vinculados', compact('pedidos', 'produtos', 'clientes'));
    }

    public function visualizarPedidosVinculadosFiltrar(Request $request) {
        $cod_transportadora = Auth::user()->getAuthIdentifier();

        $pedidos = PedidosVinculadosTransportadora::when($request->get('num_pedido') != "", function ($query) use ($request) {
                                                $query->where('NUM_PEDIDO', $request->get('num_pedido'));
                                        })->when($request->get('produto') != "0", function ($query) use ($request){
                                                $query->where('COD_PRODUTO', $request->get('produto'));
                                        })->when($request->get('data') != "", function ($query) use ($request){
                                                $query->where('DATA', $request->get('data'));
                                        })->when($request->get('cliente') != "0", function ($query) use ($request){
                                                $query->where('COD_CLIENTE', $request->get('cliente'));
                                        })->where('COD_TRANSPORTADORA', $cod_transportadora)->with('cliente')->with('produto')->orderBy('CODIGO')->get();

                                        $produtos = DB::select('SELECT produtos.DESCRICAO, produtos.CODIGO FROM agendamentos, produtos WHERE agendamentos.COD_PRODUTO = produtos.CODIGO AND agendamentos.COD_TRANSPORTADORA = '.$cod_transportadora.' GROUP BY produtos.DESCRICAO');
                                        $clientes = DB::select('SELECT clientes.NOME, clientes.CODIGO FROM agendamentos, clientes WHERE agendamentos.COD_CLIENTE = clientes.CODIGO AND agendamentos.COD_TRANSPORTADORA = '.$cod_transportadora.' GROUP BY clientes.NOME');
        
        return view('transportadora.pedidos_vinculados', compact('pedidos', 'produtos', 'clientes'));
    }

    public function verDetalhe($cod_agendamento) {
        $agendamento = Agendamento::where('CODIGO', $cod_agendamento)->with('tipoVeiculo')->with('embalagem')->first();

        if($agendamento != null) {
                return view('transportadora.agendamento_detalhes', compact('agendamento'));
        }
    }

}
