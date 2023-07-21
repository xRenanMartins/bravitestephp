<?php

namespace App\Http\Controllers;

use App\Jobs\CategoryStoreUpdate;
use Illuminate\Http\Request;
use App\Rules\Showcase\V2\StoreShowcase;
use App\Rules\Showcase\V2\UpdateShowcase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Packk\Core\Jobs\Admin\CategoryNearUpdate;
use Packk\Core\Jobs\SendShowcaseFeedEvent;
use Packk\Core\Models\Showcase;
use Packk\Core\Models\Category;

class ShowcaseController extends Controller
{
    public function index(Request $request)
    {
        return Showcase::query()
            ->like('titulo', $request->titulo)
            ->like('identifier', $request->identifier)
            ->identic('ativo', $request->ativo)
            ->orderByDesc('created_at')->simplePaginate($request->length);
    }

    public function edit(Request $request, $id)
    {
        $vitrine = Showcase::find($id);
        if (!isset($vitrine->address) && $vitrine->is_office) {
            $vitrine->type_showcase = 0; // delivery OFFICE
        } else if (isset($vitrine->address) && $vitrine->is_office) {
            $vitrine->type_showcase = 4;  // LOCAL OFFICE
        } else if (!isset($vitrine->address) && !$vitrine->is_office) {
            $vitrine->type_showcase = 1;  // DELIVERY
        } else if (isset($vitrine->address) && !$vitrine->is_office) {
            $vitrine->type_showcase = 2;// LOCAL
        }
        $vitrine->setAttribute('endereco', $vitrine->address);
        $vitrine->link = $vitrine->link();
        $vitrine->lojas = collect($vitrine->metadados_lojas)->pluck('id');
        $vitrine->categories = Category::where("vitrine_id", $id)->selectRaw("id, nome as name")->where("ativo", true)->get();
        $dias = ['Domingo', 'Segunda-feira', 'Terça-feira', 'Quarta-feira', 'Quinta-feira', 'Sexta-feira', 'Sábado'];
        $intevaloDia = 48;
        $intevaloHora = 2;
        $intevaloMinuto = 30;
        $horario = $vitrine->horario;
        $string = $horario;
        $horarios = explode("\r\n", chunk_split($string, $intevaloDia));
        $resp = $vitrine->toArray();
        $resp['intervalos'] = [];
        for ($i = 0; $i < sizeof($dias); $i++) {
            $intervalos = collect([]);
            $ini = -1;
            $fim = -1;
            $index = '1232';
            if (isset($string) && strlen($string) > 0) {
                for ($j = 0; $j < $intevaloDia; $j++) {
                    $index = $horarios[$i];
                    if ($horarios[$i][$j] == "1" && $ini == -1) {
                        $ini = $j;
                    }
                    if (($horarios[$i][$j] == "0" && $ini != -1) || ($ini != -1 && $j == $intevaloDia - 1)) {
                        $intervalo = new \stdClass();
                        $valueHourIni = $ini / $intevaloHora;
                        $valueMinuteIni = $ini % $intevaloHora;
                        $valueHourFim = $j / $intevaloHora;
                        $valueMinuteFim = $j % $intevaloHora;

                        if ($index[$intevaloDia - 1] == 1) {
                            $valueHourFim += 1;
                            $valueMinuteFim = 0;
                        }

                        $intervalo->inicio = sprintf('%02d', $valueHourIni) . ":" . ($valueMinuteIni == 0 ? "00" : $valueMinuteIni * $intevaloMinuto);
                        $intervalo->fim = sprintf("%02d", $valueHourFim) . ":" . ($valueMinuteFim == 0 ? "00" : ($valueMinuteFim) * $intevaloMinuto);
                        $intervalos->push($intervalo);
                        $fim = -1;
                        $ini = -1;
                    }
                }
            }

            $resp['intervalos'][$i] = [
                'dia' => $dias[$i],
                'horario' => $intervalos
            ];
        }

        return response()->json($resp);
    }

    public function store(Request $request)
    {
        $payload = $request->validate(self::rule());

        $resp = new StoreShowcase();
        $data = $resp->execute($payload);

        return response([
            'success' => true,
            'data' => $data
        ]);
    }

    public function update(Request $request, $id)
    {
        $payload = $request->validate(self::rule());
        $resp = new UpdateShowcase();
        $data = $resp->execute($payload, $id);

        return response([
            'success' => true,
            'data' => $data
        ]);
    }

    public function destroy($id)
    {
        $categories = Category::select('id')->where('vitrine_id', $id)->get();
        Showcase::destroy($id);
        dispatch(new SendShowcaseFeedEvent($id, 'showcase.destroy'));

        foreach ($categories as $category) {
            dispatch(new CategoryNearUpdate($category->id));
        }

        return response(['success' => true]);
    }

    private static function rule()
    {
        return [
            'mostrar_loja' => 'sometimes',
            'lojas_categoria' => 'sometimes',
            'concierge' => 'sometimes',
            'titulo' => 'sometimes',
            'descricao' => 'sometimes',
            'ordem' => 'sometimes',
            'visualizacao' => 'sometimes',
            'raio' => 'sometimes',
            'is_office' => 'sometimes',
            'identifier' => 'required',
            'tipo_concierge' => 'sometimes',
            'place_id' => 'sometimes',
            'endereco' => 'sometimes',
            'numero' => 'sometimes',
            'bairro' => 'sometimes',
            'cidade' => 'sometimes',
            'cep' => 'sometimes',
            'ativo' => 'sometimes',
            'mostrar_categorias' => 'sometimes',
            'abrir_loja' => 'sometimes',
            'latitude' => 'sometimes',
            'longitude' => 'sometimes',
            'lojas' => 'sometimes',
            'type_showcase' => 'sometimes',
            'imagemName' => 'sometimes',
            'imagem' => 'sometimes',
            'imagemFundoName' => 'sometimes',
            'imagem_fundo' => 'sometimes',
            'start_date' => 'sometimes',
            'end_date' => 'sometimes',
            'vitrine_horario' => 'sometimes',
            'link_extern' => 'sometimes',
            'type' => 'required|in:SERVICO_CONCIERGE,PRODUTO_CONCIERGE,NORMAL,NORMAL_WHITELIST,NORMAL_LINK_EXTERN',
        ];
    }
}