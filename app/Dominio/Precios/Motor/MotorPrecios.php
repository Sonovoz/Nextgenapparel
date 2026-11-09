<?php

namespace App\Dominio\Precios\Motor;

use App\Dominio\Precios\Ambito;
use App\Dominio\Precios\AplicaA;
use App\Dominio\Precios\Efecto;
use App\Dominio\Precios\Importe;
use App\Dominio\Precios\Impuestos;
use App\Dominio\Precios\MetodoEnvio;
use App\Dominio\Precios\RepartoPack;
use App\Dominio\Precios\TipoMinimo;
use App\Dominio\Precios\TipoRegla;
use App\Models\Ajuste;
use App\Models\Caja;
use App\Models\Cupon;
use App\Models\GrupoCliente;
use App\Models\Pack;
use App\Models\Prenda;
use App\Models\ReglaMinimo;
use App\Models\ReglaPrecio;
use App\Models\Talla;
use App\Models\Variante;
use App\Models\ZonaEnvio;

/**
 * Motor de precios (ficha § 6 y § 6 bis). Determinista: los mismos datos dan
 * siempre el mismo resultado. Todo en céntimos y sin IVA hasta el último paso.
 *
 * Orden: 1 prendas · 2 impresión · 3-4 ofertas (tramos y combinadas,
 * excluyentes: la mejor para el cliente) · 5 cupón · 6 mínimos · 7 envío ·
 * 8 impuestos. Los packs tienen precio cerrado: no entran en ofertas ni
 * cupones y cumplen el mínimo por sí solos (D107).
 *
 * La IA nunca calcula precios: como mucho, explica este resultado.
 */
final class MotorPrecios
{
    /** @var list<Mensaje> */
    private array $avisos = [];

    /** @var list<Mensaje> */
    private array $impedimentos = [];

    public function calcular(Cesta $cesta): Resultado
    {
        $this->avisos = [];
        $this->impedimentos = [];

        // 1-2. Prendas e impresión
        $lineas = [];
        foreach ($cesta->lineas as $linea) {
            if ($calculada = $this->valorarLinea($linea)) {
                $lineas[] = $calculada;
            }
        }
        foreach ($cesta->packs as $pack) {
            if ($calculada = $this->valorarPack($pack, $cesta)) {
                $lineas[] = $calculada;
            }
        }
        $sueltas = array_values(array_filter($lineas, fn (LineaCalculada $l) => $l->admiteDescuentos));

        // 3-4. Ofertas
        $oferta = $this->aplicarOferta($sueltas, $cesta);

        // 5. Cupón
        $cupon = $cesta->cupon === null || trim($cesta->cupon) === '' ? null : $this->aplicarCupon($cesta, $sueltas, $lineas);

        // 6. Mínimos
        if ($cesta->lineas === [] && $cesta->packs === []) {
            $this->impedimentos[] = new Mensaje('cesta_vacia');
        } elseif ($lineas !== []) {
            $this->comprobarMinimos($sueltas, count($lineas) > count($sueltas), $cesta);
        }

        // 7. Envío
        $envio = $cesta->destino === null || $lineas === [] ? null : $this->calcularEnvio($cesta->destino, $lineas);

        // 8. Impuestos
        $impuestos = $this->calcularImpuestos($lineas, $envio, $cesta->recargoEquivalencia);

        return new Resultado($lineas, $oferta, $cupon, $envio, $impuestos, $this->avisos, $this->impedimentos);
    }

    // ── 1-2. Prendas e impresión ──────────────────────────────────────

    /** Unidades por talla en la entrada: enteros de 1 a MAXIMO_UNIDADES (el 0 se ignora). */
    public const MAXIMO_UNIDADES = 10000;

    /** @return array<int, int>|null  null si hay alguna cantidad no válida */
    private function cantidades(array $tallas): ?array
    {
        $limpias = [];
        foreach ($tallas as $talla => $unidades) {
            if (! is_int($unidades) || $unidades < 0 || $unidades > self::MAXIMO_UNIDADES || ! is_numeric($talla)) {
                return null;
            }
            if ($unidades > 0) {
                $limpias[(int) $talla] = $unidades;
            }
        }

        return $limpias;
    }

    private function valorarLinea(LineaCesta $linea): ?LineaCalculada
    {
        $prenda = Prenda::where('activa', true)->with('familia')->find($linea->prendaId);
        $tallas = $this->cantidades($linea->tallas);
        if ($tallas === null) {
            $this->impedimentos[] = new Mensaje('cantidad_no_valida', ['maximo' => self::MAXIMO_UNIDADES]);

            return null;
        }
        if ($prenda === null || $tallas === []) {
            $this->impedimentos[] = new Mensaje('linea_no_disponible');

            return null;
        }

        $variantes = $prenda->variantes()->aLaVenta()->where('color_id', $linea->colorId)
            ->whereIn('talla_id', array_keys($tallas))->get()->keyBy('talla_id');
        $prendas = 0;
        $peso = 0;
        foreach ($tallas as $tallaId => $unidades) {
            $variante = $variantes->get($tallaId);
            if (! $variante instanceof Variante) {
                $this->impedimentos[] = new Mensaje('variante_no_disponible', ['prenda' => ['modelo' => 'prenda', 'id' => $prenda->id], 'talla' => (string) Talla::find($tallaId)?->nombre]);

                return null;
            }
            $prendas += $variante->precio_base * $unidades;
            $peso += $variante->peso_gramos * $unidades;
        }

        $unidades = array_sum($tallas);
        $zonas = $prenda->zonas()->where('zonas_impresion.activa', true)->whereIn('zonas_impresion.id', $linea->zonas)->get();
        if ($zonas->isEmpty() || $zonas->count() !== count(array_unique($linea->zonas))) {
            // La prenda nunca se vende sin imprimir, ni en una zona que no ofrece (D118).
            $this->impedimentos[] = new Mensaje('linea_sin_impresion', ['prenda' => ['modelo' => 'prenda', 'id' => $prenda->id]]);

            return null;
        }
        $impresion = (int) $zonas->sum(fn ($z) => $z->pivot->precio) * $unidades;
        [$tipoIva, $recargo] = Impuestos::tipos($prenda->clase_impuesto_id);

        return new LineaCalculada('prenda', $prenda->id, $prenda->nombre, $prenda->familia_id, $unidades, $tallas,
            $prendas, $impresion, $tipoIva, $recargo, $peso, admiteDescuentos: true);
    }

    private function valorarPack(PackCesta $elegido, Cesta $cesta): ?LineaCalculada
    {
        $pack = Pack::vigentes($cesta->ahora())->with(['lineas.prenda', 'zonas'])->find($elegido->packId);
        if ($pack === null) {
            $this->impedimentos[] = new Mensaje('pack_no_disponible');

            return null;
        }

        $peso = 0;
        $extra = 0;
        $tallasTotales = [];
        foreach ($pack->lineas as $lineaPack) {
            // Prenda en la papelera (null) o retirada: el pack deja de venderse.
            if ($lineaPack->prenda === null || ! $lineaPack->prenda->activa) {
                $this->impedimentos[] = new Mensaje('pack_no_disponible');

                return null;
            }
            $eleccion = $elegido->elecciones[$lineaPack->id] ?? [];
            if ($pack->reparto === RepartoPack::Fijo) {
                $color = (int) ($lineaPack->colores[0] ?? 0);
                $tallas = array_map('intval', $lineaPack->reparto_tallas ?? []);
            } else {
                $color = (int) ($eleccion['color'] ?? ($lineaPack->colores[0] ?? 0));
                $tallas = $this->cantidades($eleccion['tallas'] ?? []);
                if ($tallas === null) {
                    $this->impedimentos[] = new Mensaje('cantidad_no_valida', ['maximo' => self::MAXIMO_UNIDADES]);

                    return null;
                }
                $permitidas = array_map('intval', $lineaPack->tallas_permitidas ?? []);
                $repartidas = array_sum($tallas);
                if (! in_array($color, array_map('intval', $lineaPack->colores), true)
                    || array_diff(array_keys($tallas), $permitidas) !== []) {
                    $this->impedimentos[] = new Mensaje('pack_eleccion_no_valida', ['pack' => ['modelo' => 'pack', 'id' => $pack->id]]);

                    return null;
                }
                if ($repartidas !== $lineaPack->unidades) {
                    $faltan = $lineaPack->unidades - $repartidas;
                    $this->impedimentos[] = $faltan > 0
                        ? new Mensaje('pack_falta_repartir', ['pack' => ['modelo' => 'pack', 'id' => $pack->id], 'faltan' => $faltan], $faltan)
                        : new Mensaje('pack_sobran', ['pack' => ['modelo' => 'pack', 'id' => $pack->id], 'sobran' => -$faltan], -$faltan);

                    return null;
                }
            }

            $variantes = $lineaPack->prenda->variantes()->aLaVenta()->where('color_id', $color)->whereIn('talla_id', array_keys($tallas))->get()->keyBy('talla_id');
            foreach ($tallas as $tallaId => $unidades) {
                if (! $variantes->has($tallaId)) {
                    $this->impedimentos[] = new Mensaje('pack_no_disponible');

                    return null;
                }
                $peso += $variantes[$tallaId]->peso_gramos * $unidades;
                $tallasTotales[$tallaId] = ($tallasTotales[$tallaId] ?? 0) + $unidades;
            }

            // Zonas extra: se cobran con la tarifa de la prenda (D106).
            foreach (array_diff(array_unique($elegido->zonasExtra), $pack->zonas->pluck('id')->all()) as $zonaId) {
                $precioZona = $lineaPack->prenda->zonas()->where('zonas_impresion.activa', true)->where('zonas_impresion.id', $zonaId)->first()?->pivot->precio;
                if ($precioZona === null) {
                    $this->impedimentos[] = new Mensaje('linea_sin_impresion', ['prenda' => ['modelo' => 'prenda', 'id' => $lineaPack->prenda_id]]);

                    return null;
                }
                $extra += (int) $precioZona * $lineaPack->unidades;
            }
        }

        [$tipoIva, $recargo] = Impuestos::tipos($pack->clase_impuesto_id);

        return new LineaCalculada('pack', $pack->id, $pack->nombre, null, $pack->unidades(), $tallasTotales,
            $pack->precio, $extra, $tipoIva, $recargo, $peso, admiteDescuentos: false);
    }

    // ── 3-4. Ofertas ──────────────────────────────────────────────────

    /** @param  list<LineaCalculada>  $sueltas */
    private function aplicarOferta(array $sueltas, Cesta $cesta): ?array
    {
        if ($sueltas === []) {
            return null;
        }

        $mejor = null;
        $umbralCercana = (int) Ajuste::obtener('ofertas.aviso_cercana');
        $cercanas = [];

        foreach (ReglaPrecio::vigentes($cesta->ahora())->orderByDesc('prioridad')->orderBy('id')->get() as $regla) {
            [$cumple, $progreso, $faltan] = $this->evaluarCondiciones($regla, $sueltas);
            if (! $cumple) {
                if ($progreso >= $umbralCercana && $faltan !== []) {
                    $cercanas[] = new Mensaje('oferta_cercana', ['faltan' => ['lista' => $faltan, 'union' => 'y'], 'oferta' => ['modelo' => 'regla', 'id' => $regla->id]]);
                }

                continue;
            }

            $reparto = $this->descuentoDe($regla, $sueltas);
            $importe = array_sum($reparto);
            if ($importe > 0 && ($mejor === null || $importe > $mejor['importe'])) {
                $mejor = ['regla' => $regla, 'importe' => $importe, 'reparto' => $reparto];
            }
        }

        // Tarifa de su grupo (D192): compite con las ofertas y se aplica la que más ahorra (no se suman).
        $grupo = $cesta->grupoId === null ? null : GrupoCliente::where('activo', true)->find($cesta->grupoId);
        if ($grupo !== null && $grupo->descuento > 0) {
            $reparto = self::descontar(Efecto::Porcentaje, $grupo->descuento, array_map(fn (LineaCalculada $l) => $l->bruto(), $sueltas));
            $importe = array_sum($reparto);
            if ($importe > 0 && ($mejor === null || $importe > $mejor['importe'])) {
                foreach ($reparto as $indice => $descuento) {
                    $sueltas[$indice]->descuento = $descuento;
                }
                $this->avisos[] = new Mensaje('tarifa_grupo_aplicada', ['grupo' => ['modelo' => 'grupo', 'id' => $grupo->id], 'ahorro' => ['euros' => $importe]]);

                return ['id' => null, 'grupo' => $grupo->id, 'nombre' => $grupo->nombre, 'importe' => $importe];
            }
        }

        if ($mejor === null) {
            array_push($this->avisos, ...$cercanas);

            return null;
        }

        foreach ($mejor['reparto'] as $indice => $descuento) {
            $sueltas[$indice]->descuento = $descuento;
        }
        $regla = $mejor['regla'];
        $this->avisos[] = new Mensaje($regla->tipo === TipoRegla::Combinada ? 'oferta_combinada_aplicada' : 'oferta_aplicada',
            ['oferta' => ['modelo' => 'regla', 'id' => $regla->id], 'ahorro' => ['euros' => $mejor['importe']]]);
        // Las cercanas de otras ofertas siguen siendo útiles («te faltan 3 para…»).
        array_push($this->avisos, ...$cercanas);

        return ['id' => $regla->id, 'nombre' => $regla->nombre, 'importe' => $mejor['importe']];
    }

    /**
     * @param  list<LineaCalculada>  $sueltas
     * @return array{0: bool, 1: int, 2: list<array<string, mixed>>} cumple, progreso (centésimas de %), qué falta
     */
    private function evaluarCondiciones(ReglaPrecio $regla, array $sueltas): array
    {
        $grupos = $regla->tipo === TipoRegla::TramoCantidad
            ? [['ambito' => $regla->ambito->value, 'ids' => array_filter([$regla->ambito_id]), 'minimo' => (int) ($regla->condiciones['minimo_unidades'] ?? 0)]]
            : ($regla->condiciones['grupos'] ?? []);
        if ($grupos === []) {
            return [false, 0, []];
        }

        $necesario = 0;
        $logrado = 0;
        $faltas = [];
        foreach ($grupos as $grupo) {
            $minimo = (int) ($grupo['minimo'] ?? 0);
            $ambito = Ambito::tryFrom((string) ($grupo['ambito'] ?? 'catalogo'));
            $ids = array_map('intval', (array) ($grupo['ids'] ?? []));
            // Condición mal formada (p. ej. importada a mano): la oferta no se aplica.
            if ($ambito === null || $minimo < 1 || ($ambito !== Ambito::Catalogo && $ids === [])) {
                return [false, 0, []];
            }
            $enGrupo = array_filter($sueltas, fn (LineaCalculada $l) => $this->enAmbito($l, $ambito, $ids));
            $unidades = array_sum(array_map(fn ($l) => $l->unidades, $enGrupo));
            $necesario += $minimo;
            $logrado += min($unidades, $minimo);
            if ($unidades < $minimo) {
                $faltas[] = ['n' => $minimo - $unidades, 'de' => $this->nombreGrupo($ambito, $ids, $enGrupo)];
            }
        }

        $progreso = $necesario === 0 ? 0 : intdiv($logrado * 10000, $necesario);

        return [$faltas === [], $progreso, $faltas];
    }

    /**
     * Referencia al nombre de un grupo (se traduce al mostrarlo): «prendas»,
     * «camisetas», «polos o sudaderas» o, si ya lleva una de ellas, «sudaderas».
     *
     * @return array<string, mixed>
     */
    private function nombreGrupo(Ambito $ambito, array $ids, array $enGrupo): array
    {
        if ($ambito === Ambito::Catalogo) {
            return ['t' => 'precios.prendas'];
        }
        if (count($ids) > 1 && count($presentes = array_values(array_unique(array_map(fn ($l) => $ambito === Ambito::Familia ? $l->familiaId : $l->referencia, $enGrupo)))) === 1) {
            $ids = $presentes;
        }

        return $ambito === Ambito::Familia ? ['familias' => array_values($ids)] : ['prendas' => array_values($ids)];
    }

    /** @param  list<int>  $ids */
    private function enAmbito(LineaCalculada $linea, Ambito $ambito, array $ids): bool
    {
        return match ($ambito) {
            Ambito::Catalogo => true,
            Ambito::Familia => in_array($linea->familiaId, $ids, true),
            Ambito::Prenda => $linea->tipo === 'prenda' && in_array($linea->referencia, $ids, true),
        };
    }

    /**
     * @param  list<LineaCalculada>  $sueltas
     * @return array<int, int> descuento por índice de línea
     */
    private function descuentoDe(ReglaPrecio $regla, array $sueltas): array
    {
        $ids = array_filter([$regla->ambito_id]);
        $bases = [];
        foreach ($sueltas as $i => $linea) {
            if ($this->enAmbito($linea, $regla->ambito, $ids)) {
                $bases[$i] = match ($regla->aplica_a) {
                    AplicaA::Prendas => $linea->prendas,
                    AplicaA::Impresion => $linea->impresion,
                    AplicaA::Todo => $linea->bruto(),
                };
            }
        }

        return self::descontar($regla->efecto, $regla->valor, $bases);
    }

    /**
     * Descuento sobre unas bases: el porcentaje se calcula sobre el total (un
     * solo redondeo) y se reparte entre las líneas; nunca supera la base, así
     * que ningún neto queda en negativo aunque se configure más del 100 %.
     *
     * @param  array<int, int>  $bases
     * @return array<int, int>
     */
    private static function descontar(Efecto $efecto, int $valor, array $bases): array
    {
        $total = array_sum($bases);
        $importe = $efecto === Efecto::Porcentaje ? Importe::porcentaje($total, min($valor, 10000)) : $valor;

        return Importe::repartir(min($importe, $total), $bases);
    }

    // ── 5. Cupón ──────────────────────────────────────────────────────

    /**
     * @param  list<LineaCalculada>  $sueltas
     * @param  list<LineaCalculada>  $lineas
     */
    private function aplicarCupon(Cesta $cesta, array $sueltas, array $lineas): ?array
    {
        $codigo = Cupon::normalizar($cesta->cupon);
        $cupon = Cupon::where('codigo', $codigo)->first();
        $ahora = $cesta->ahora();

        $motivo = match (true) {
            $cupon === null || ! $cupon->activo => 'cupon_no_existe',
            $cupon->desde !== null && $cupon->desde > $ahora, $cupon->hasta !== null && $cupon->hasta < $ahora => 'cupon_caducado',
            $cupon->usos_maximos !== null && $cupon->usos >= $cupon->usos_maximos => 'cupon_agotado',
            $cupon->un_uso_por_cliente && in_array($codigo, array_map([Cupon::class, 'normalizar'], $cesta->cuponesUsados), true) => 'cupon_ya_usado',
            $sueltas === [] && $lineas !== [] => 'cupon_packs',
            default => null,
        };

        $neto = array_sum(array_map(fn (LineaCalculada $l) => $l->neto(), $sueltas));
        if ($motivo === null && $cupon->minimo_importe !== null && $neto < $cupon->minimo_importe) {
            $this->avisos[] = new Mensaje('cupon_minimo', ['codigo' => $codigo, 'minimo' => ['euros' => $cupon->minimo_importe]]);

            return null;
        }
        if ($motivo !== null) {
            $this->avisos[] = new Mensaje($motivo, ['codigo' => $codigo]);

            return null;
        }

        $bases = array_map(fn (LineaCalculada $l) => $l->neto(), $sueltas);
        $reparto = self::descontar($cupon->tipo, $cupon->valor, $bases);
        foreach ($reparto as $i => $importe) {
            $sueltas[$i]->cupon = $importe;
        }
        $importe = array_sum($reparto);
        $this->avisos[] = new Mensaje(count($sueltas) < count($lineas) ? 'cupon_aplicado_sin_packs' : 'cupon_aplicado', ['codigo' => $codigo, 'ahorro' => ['euros' => $importe]]);

        return ['codigo' => $codigo, 'importe' => $importe];
    }

    // ── 6. Mínimos ────────────────────────────────────────────────────

    /** @param  list<LineaCalculada>  $sueltas */
    private function comprobarMinimos(array $sueltas, bool $hayPack, Cesta $cesta): void
    {
        foreach (ReglaMinimo::vigentes($cesta->ahora())->orderByDesc('prioridad')->orderBy('id')->get() as $regla) {
            // Un pack cumple por sí solo el mínimo general de unidades e importe
            // (D107); el mínimo por talla sigue valiendo para las sueltas (D127).
            if ($regla->ambito === Ambito::Catalogo && $hayPack && $regla->tipo !== TipoMinimo::UnidadesPorTalla) {
                continue;
            }
            $ids = array_filter([$regla->ambito_id]);
            $enAmbito = array_values(array_filter($sueltas, fn (LineaCalculada $l) => $this->enAmbito($l, $regla->ambito, $ids)));
            if ($regla->ambito !== Ambito::Catalogo && $enAmbito === []) {
                continue;
            }
            $donde = $regla->ambito === Ambito::Catalogo ? ['t' => 'precios.el_pedido'] : $this->nombreGrupo($regla->ambito, $ids, []);

            switch ($regla->tipo) {
                case TipoMinimo::UnidadesTotal:
                    $llevas = array_sum(array_map(fn ($l) => $l->unidades, $enAmbito));
                    if ($llevas < $regla->valor) {
                        $this->impedimentos[] = new Mensaje('minimo_unidades', ['donde' => $donde, 'minimo' => $regla->valor, 'llevas' => $llevas, 'faltan' => $regla->valor - $llevas], $regla->valor - $llevas);
                    }
                    break;
                case TipoMinimo::UnidadesPorTalla:
                    foreach ($enAmbito as $linea) {
                        foreach ($linea->tallas as $tallaId => $unidades) {
                            if ($unidades < $regla->valor) {
                                $this->impedimentos[] = new Mensaje('minimo_por_talla', ['minimo' => $regla->valor, 'prenda' => ['modelo' => 'prenda', 'id' => $linea->referencia], 'talla' => (string) Talla::find($tallaId)?->nombre, 'llevas' => $unidades]);
                            }
                        }
                    }
                    break;
                case TipoMinimo::ImporteMinimo:
                    $importe = array_sum(array_map(fn ($l) => $l->neto(), $enAmbito));
                    if ($importe < $regla->valor) {
                        $this->impedimentos[] = new Mensaje('minimo_importe', ['donde' => $donde, 'minimo' => ['euros' => $regla->valor], 'faltan' => ['euros' => $regla->valor - $importe]]);
                    }
                    break;
            }
        }
    }

    // ── 7. Envío ──────────────────────────────────────────────────────

    /** @param  list<LineaCalculada>  $lineas */
    private function calcularEnvio(Destino $destino, array $lineas): ?array
    {
        $zona = ZonaEnvio::paraDestino($destino->pais, $destino->codigoPostal)?->load('tarifas');

        if ($zona === null) {
            $this->impedimentos[] = new Mensaje('sin_envio');

            return null;
        }

        $peso = array_sum(array_map(fn ($l) => $l->pesoGramos, $lineas));
        $unidades = array_sum(array_map(fn ($l) => $l->unidades, $lineas));
        $mercancia = array_sum(array_map(fn ($l) => $l->neto(), $lineas));
        $bultos = null;

        if ($zona->metodo === MetodoEnvio::PorKilo) {
            $tarifa = $zona->tarifas->first(fn ($t) => $t->hasta_gramos >= $peso);
            $ultima = $zona->tarifas->last();
            if ($tarifa !== null) {
                $importe = $tarifa->precio;
            } elseif ($ultima !== null && $zona->precio_kg_extra !== null) {
                $importe = $ultima->precio + (int) ceil(($peso - $ultima->hasta_gramos) / 1000) * $zona->precio_kg_extra;
            } else {
                $this->impedimentos[] = new Mensaje('sin_envio');

                return null;
            }
        } else {
            $caja = Caja::where('activa', true)->orderByDesc('capacidad_prendas')->first();
            if ($caja === null || $zona->precio_caja === null) {
                $this->impedimentos[] = new Mensaje('sin_envio');

                return null;
            }
            $bultos = max((int) ceil($unidades / $caja->capacidad_prendas), (int) ceil($peso / $caja->peso_max_gramos), 1);
            $importe = $bultos * $zona->precio_caja;
        }

        if ($zona->gratis_desde !== null && $mercancia >= $zona->gratis_desde) {
            $importe = 0;
            $this->avisos[] = new Mensaje('envio_gratis');
        } elseif ($zona->gratis_desde !== null) {
            $this->avisos[] = new Mensaje('envio_gratis_cerca', ['faltan' => ['euros' => $zona->gratis_desde - $mercancia]]);
        }

        return ['zona' => $zona->nombre, 'metodo' => $zona->metodo->value, 'importe' => $importe, 'plazo_dias' => $zona->plazo_dias, 'peso_gramos' => $peso, 'bultos' => $bultos];
    }

    // ── 8. Impuestos ──────────────────────────────────────────────────

    /** @param  list<LineaCalculada>  $lineas */
    private function calcularImpuestos(array $lineas, ?array $envio, bool $conRecargo): array
    {
        // Sin recargo se agrupa solo por tipo de IVA (un redondeo por tipo).
        $bases = [];
        foreach ($lineas as $linea) {
            $clave = $linea->tipoIva.'|'.($conRecargo ? $linea->recargo : 0);
            $bases[$clave] = ($bases[$clave] ?? 0) + $linea->neto();
        }
        $envioImporte = $envio['importe'] ?? 0;
        if ($envioImporte > 0) {
            // El envío sigue el tipo de la mercancía, repartido en proporción a
            // cada base (D122); sin mercancía con base, el de la clase por defecto.
            if (array_sum($bases) > 0) {
                foreach (Importe::repartir($envioImporte, $bases) as $clave => $parte) {
                    $bases[$clave] += $parte;
                }
            } else {
                [$tipo, $recargo] = Impuestos::tipos(null);
                $clave = $tipo.'|'.($conRecargo ? $recargo : 0);
                $bases[$clave] = ($bases[$clave] ?? 0) + $envioImporte;
            }
        }

        $impuestos = [];
        foreach ($bases as $clave => $base) {
            [$tipo, $recargo] = array_map('intval', explode('|', $clave));
            $impuestos[] = [
                'tipo_iva' => $tipo, 'recargo' => $recargo, 'base' => $base,
                'cuota' => Importe::porcentaje($base, $tipo),
                'cuota_recargo' => $conRecargo ? Importe::porcentaje($base, $recargo) : 0,
            ];
        }

        return $impuestos;
    }
}
