<?php

namespace Tests\Feature\Carrito;

use App\Dominio\Carrito\CarritoActual;
use App\Dominio\Carrito\EditarCarrito;
use App\Dominio\Carrito\FusionarCarrito;
use App\Dominio\Carrito\LimpiarCarritos;
use App\Dominio\Carrito\LineaNoValida;
use App\Dominio\Carrito\ValorarCarrito;
use App\Dominio\Catalogo\Cara;
use App\Dominio\Clientes\BorrarCliente;
use App\Dominio\Disenos\Dueno;
use App\Dominio\Disenos\LimpiarDisenos;
use App\Dominio\Precios\Motor\Cesta;
use App\Dominio\Precios\Motor\LineaCesta;
use App\Dominio\Precios\Motor\MotorPrecios;
use App\Models\Ajuste;
use App\Models\Carrito;
use App\Models\Cliente;
use App\Models\Color;
use App\Models\Cupon;
use App\Models\Diseno;
use App\Models\Familia;
use App\Models\ImpresionCarrito;
use App\Models\LineaCarrito;
use App\Models\Pack;
use App\Models\Prenda;
use App\Models\Talla;
use App\Models\User;
use App\Models\Variante;
use App\Models\ZonaImpresion;
use App\Policies\CatalogoPolicy;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\URL;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Carrito persistente (paso 5.5): qué se puede añadir, qué deja de poder comprarse
 * cuando cambia el catálogo, que el precio sale siempre del motor, la suma de carritos
 * al entrar en la cuenta y la retención.
 */
class CarritoTest extends TestCase
{
    use RefreshDatabase;

    private const CLAVE = 'contrasena-segura-1';

    private Prenda $camiseta;

    private Color $negro;

    /** @var array<string, int> */
    private array $tallas = [];

    /** @var array<string, int> */
    private array $zonas = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->negro = Color::factory()->create(['nombre' => ['es' => 'Negro']]);
        foreach (['M', 'L', 'XL'] as $orden => $nombre) {
            $this->tallas[$nombre] = Talla::factory()->create(['nombre' => $nombre, 'orden' => $orden])->id;
        }
        foreach (['frontal' => Cara::Delantera, 'espalda' => Cara::Trasera, 'manga' => Cara::Delantera] as $clave => $cara) {
            $this->zonas[$clave] = ZonaImpresion::factory()->create(['clave' => $clave, 'cara' => $cara, 'ancho_max_mm' => 280, 'alto_max_mm' => 380])->id;
        }
        $this->camiseta = Prenda::factory()->for(Familia::factory()->create(['nombre' => ['es' => 'Camisetas']]))->create(['nombre' => ['es' => 'Camiseta']]);
        foreach ($this->tallas as $talla) {
            Variante::factory()->for($this->camiseta)->create(['color_id' => $this->negro->id, 'talla_id' => $talla, 'precio_base' => 400, 'peso_gramos' => 150]);
        }
        $this->camiseta->zonas()->attach($this->zonas['frontal'], ['precio' => 300, 'coste' => 100]);
        $this->camiseta->zonas()->attach($this->zonas['espalda'], ['precio' => 400, 'coste' => 100]);
        // Talla M: 700 mm de largo y 500 de ancho; L y XL, más grandes.
        foreach (['M' => [700, 500], 'L' => [740, 540], 'XL' => [760, 560]] as $talla => [$largo, $ancho]) {
            $this->camiseta->medidas()->create(['talla_id' => $this->tallas[$talla], 'largo_mm' => $largo, 'ancho_mm' => $ancho]);
        }
    }

    /* ---------- Añadir ---------- */

    public function test_anade_una_prenda_sin_guardar_precios_y_el_motor_la_valora(): void
    {
        $dueno = Dueno::sesion('sesion-a');
        $diseno = $this->diseno(['propietario' => 'sesion-a']);
        $carrito = Carrito::create();

        $linea = $this->editar()->anadirPrenda($carrito, $dueno, $this->camiseta->id, $this->negro->id,
            [$this->tallas['M'] => 10, $this->tallas['L'] => 5, $this->tallas['XL'] => 0], [$this->zonas['frontal'] => $this->impresion($diseno)]);

        $this->assertSame([$this->tallas['M'] => 10, $this->tallas['L'] => 5], $linea->fresh()->tallas);
        $this->assertSame($diseno->id, $linea->impresiones->sole()->diseno_id);
        $this->assertSame(['ancho_mm' => 200, 'alto_mm' => 150, 'desde_cuello_mm' => 75, 'desde_centro_mm' => 0],
            $linea->impresiones->sole()->only(['ancho_mm', 'alto_mm', 'desde_cuello_mm', 'desde_centro_mm']));

        $valoracion = app(ValorarCarrito::class)($carrito);
        $motor = app(MotorPrecios::class)->calcular(new Cesta([new LineaCesta($this->camiseta->id, $this->negro->id, [$this->tallas['M'] => 10, $this->tallas['L'] => 5], [$this->zonas['frontal']])]));
        $this->assertSame($motor->total(), $valoracion->resultado->total());
        $this->assertSame(12705, $valoracion->resultado->total()); // 15 × (4,00 + 3,00) + 21 % IVA
        $this->assertTrue($valoracion->confirmable());
        $this->assertSame('correcta', $valoracion->lineas[0]['calidades'][$this->zonas['frontal']]['nivel']->value);
    }

    public function test_no_se_puede_usar_el_diseno_de_otro_aunque_se_conozca_su_uuid(): void
    {
        $ajeno = $this->diseno(['propietario' => 'sesion-b']);
        $deCliente = $this->diseno(['cliente_id' => Cliente::factory()->create()->id]);
        $rechazado = $this->diseno(['propietario' => 'sesion-a', 'estado' => 'rechazado']);

        foreach ([$ajeno, $deCliente, $rechazado] as $diseno) {
            $this->assertProblemas(['diseno_no_disponible'], fn () => $this->editar()->anadirPrenda(Carrito::create(), Dueno::sesion('sesion-a'),
                $this->camiseta->id, $this->negro->id, [$this->tallas['M'] => 15], [$this->zonas['frontal'] => $this->impresion($diseno)]));
        }
        $this->assertSame(0, LineaCarrito::count());
    }

    /** @return array<string, array{0: callable, 1: list<string>}> */
    public static function lineasNoValidas(): array
    {
        return [
            'zona que la prenda no ofrece' => [fn ($t) => [[$t->tallas['M'] => 15], [$t->zonas['manga'] => []]], ['zona_no_disponible']],
            'más ancho que la zona' => [fn ($t) => [[$t->tallas['M'] => 15], [$t->zonas['frontal'] => ['ancho_mm' => 281]]], ['medida_no_valida']],
            'más pequeño que el mínimo' => [fn ($t) => [[$t->tallas['M'] => 15], [$t->zonas['frontal'] => ['alto_mm' => 9]]], ['medida_no_valida']],
            'posición fuera de la prenda' => [fn ($t) => [[$t->tallas['M'] => 15], [$t->zonas['frontal'] => ['desde_centro_mm' => -1001]]], ['medida_no_valida']],
            'sin unidades' => [fn ($t) => [[$t->tallas['M'] => 0], [$t->zonas['frontal'] => []]], ['sin_unidades']],
            'unidades que no son enteras' => [fn ($t) => [[$t->tallas['M'] => '15'], [$t->zonas['frontal'] => []]], ['cantidad_no_valida']],
            'más del máximo del motor' => [fn ($t) => [[$t->tallas['M'] => MotorPrecios::MAXIMO_UNIDADES + 1], [$t->zonas['frontal'] => []]], ['cantidad_no_valida']],
            'talla que no existe' => [fn ($t) => [[999 => 15], [$t->zonas['frontal'] => []]], ['talla_no_disponible']],
            'sin diseño' => [fn ($t) => [[$t->tallas['M'] => 15], []], ['sin_impresion']],
            'diseño que no es un texto' => [fn ($t) => [[$t->tallas['M'] => 15], [$t->zonas['frontal'] => ['diseno' => ['x']]]], ['diseno_no_disponible']],
            'fuera del largo de la talla' => [fn ($t) => [[$t->tallas['M'] => 15], [$t->zonas['frontal'] => ['desde_cuello_mm' => 600]]], ['medida_no_valida']],
            'fuera del ancho de la talla' => [fn ($t) => [[$t->tallas['M'] => 15], [$t->zonas['frontal'] => ['desde_centro_mm' => 200]]], ['medida_no_valida']],
        ];
    }

    #[DataProvider('lineasNoValidas')]
    public function test_no_se_anade_una_linea_no_valida(callable $datos, array $esperados): void
    {
        $diseno = $this->diseno(['propietario' => 'sesion-a']);
        [$tallas, $zonas] = $datos($this);
        $zonas = array_map(fn (array $cambios) => [...$this->impresion($diseno), ...$cambios], $zonas);

        $this->assertProblemas($esperados, fn () => $this->editar()->anadirPrenda(Carrito::create(), Dueno::sesion('sesion-a'),
            $this->camiseta->id, $this->negro->id, $tallas, $zonas));
    }

    public function test_hay_un_maximo_de_lineas_por_carrito(): void
    {
        $carrito = Carrito::create();
        $diseno = $this->diseno(['propietario' => 'sesion-a']);
        for ($i = 0; $i < EditarCarrito::MAXIMO_LINEAS; $i++) {
            $carrito->lineas()->create(['tipo' => 'prenda', 'prenda_id' => $this->camiseta->id, 'color_id' => $this->negro->id, 'tallas' => [$this->tallas['M'] => 1]]);
        }

        $this->assertProblemas(['demasiadas_lineas'], fn () => $this->editar()->anadirPrenda($carrito, Dueno::sesion('sesion-a'),
            $this->camiseta->id, $this->negro->id, [$this->tallas['M'] => 15], [$this->zonas['frontal'] => $this->impresion($diseno)]));
    }

    /* ---------- El catálogo cambia mientras el carrito espera ---------- */

    /** @return array<string, array{0: callable, 1: string}> */
    public static function cambiosDelCatalogo(): array
    {
        return [
            'prenda retirada' => [fn ($t, $d) => $t->camiseta->update(['activa' => false]), 'prenda_no_disponible'],
            'prenda en la papelera' => [fn ($t, $d) => $t->camiseta->delete(), 'prenda_no_disponible'],
            'talla retirada' => [fn ($t, $d) => Variante::where('talla_id', $t->tallas['L'])->update(['activa' => false]), 'talla_no_disponible'],
            'color retirado' => [fn ($t, $d) => $t->negro->update(['activo' => false]), 'talla_no_disponible'],
            'zona retirada' => [fn ($t, $d) => ZonaImpresion::whereKey($t->zonas['frontal'])->update(['activa' => false]), 'zona_no_disponible'],
            'zona quitada de la prenda' => [fn ($t, $d) => $t->camiseta->zonas()->detach($t->zonas['frontal']), 'zona_no_disponible'],
            'zona más pequeña' => [fn ($t, $d) => ZonaImpresion::whereKey($t->zonas['frontal'])->update(['ancho_max_mm' => 150]), 'medida_no_valida'],
            'diseño borrado' => [fn ($t, $d) => $d->delete(), 'diseno_no_disponible'],
            'diseño rechazado en la revisión' => [fn ($t, $d) => $d->forceFill(['estado' => 'rechazado'])->save(), 'diseno_no_disponible'],
            'talla más corta en el backend' => [fn ($t, $d) => $t->camiseta->medidas()->where('talla_id', $t->tallas['M'])->update(['largo_mm' => 200]), 'medida_no_valida'],
        ];
    }

    #[DataProvider('cambiosDelCatalogo')]
    public function test_una_linea_que_deja_de_poder_comprarse_se_marca_y_no_se_cobra(callable $cambio, string $problema): void
    {
        $dueno = Dueno::sesion('sesion-a');
        $diseno = $this->diseno(['propietario' => 'sesion-a']);
        $otro = $this->diseno(['propietario' => 'sesion-a', 'sha256' => str_repeat('b', 64)]);
        $carrito = Carrito::create();
        $afectada = $this->editar()->anadirPrenda($carrito, $dueno, $this->camiseta->id, $this->negro->id,
            [$this->tallas['M'] => 10, $this->tallas['L'] => 5], [$this->zonas['frontal'] => $this->impresion($diseno)]);
        [$polo, $blanco] = $this->otraPrenda();
        $this->editar()->anadirPrenda($carrito, $dueno, $polo->id, $blanco->id, [$this->tallas['M'] => 15], [$this->zonas['espalda'] => $this->impresion($otro)]);

        $cambio($this, $diseno);
        $valoracion = app(ValorarCarrito::class)($carrito->fresh());

        $this->assertContains($problema, $valoracion->lineas[0]['problemas']);
        $this->assertSame([], $valoracion->lineas[1]['problemas']);
        $this->assertSame(15, $valoracion->resultado->unidades()); // solo el polo
        $this->assertFalse($valoracion->confirmable());
        $this->assertNotSame([], $valoracion->problemas($afectada));
    }

    /* ---------- Cupón, tallas y quitar ---------- */

    public function test_el_cupon_se_guarda_normalizado_y_lo_valida_el_motor(): void
    {
        Cupon::create(['codigo' => 'VERANO10', 'tipo' => 'porcentaje', 'valor' => 1000]);
        $carrito = $this->carritoConCamisetas(15);

        $this->editar()->ponerCupon($carrito, '  verano10 ');
        $this->assertSame('VERANO10', $carrito->fresh()->cupon);
        $this->assertSame(10500 - 1050, app(ValorarCarrito::class)($carrito->fresh())->resultado->subtotal());

        $this->editar()->ponerCupon($carrito, 'NOEXISTE');
        $valoracion = app(ValorarCarrito::class)($carrito->fresh());
        $this->assertSame(10500, $valoracion->resultado->subtotal());
        $this->assertContains('El cupón NOEXISTE no existe o no está activo.', $valoracion->resultado->textos('avisos', 'es'));

        $this->editar()->ponerCupon($carrito, '');
        $this->assertNull($carrito->fresh()->cupon);
    }

    public function test_cambiar_tallas_y_quitar_lineas(): void
    {
        $carrito = $this->carritoConCamisetas(15);
        $linea = $carrito->lineas()->sole();

        $this->editar()->cambiarTallas($carrito, $linea->uuid, [$this->tallas['M'] => 20, $this->tallas['XL'] => 2]);
        $this->assertSame([$this->tallas['M'] => 20, $this->tallas['XL'] => 2], $linea->fresh()->tallas);
        $this->assertProblemas(['sin_unidades'], fn () => $this->editar()->cambiarTallas($carrito, $linea->uuid, [$this->tallas['M'] => 0]));

        // La línea se busca en el carrito de quien compra: con el uuid de otra no se toca nada.
        $ajeno = $this->carritoConCamisetas(15, propietario: 'sesion-b');
        foreach ([fn () => $this->editar()->quitar($ajeno, $linea->uuid), fn () => $this->editar()->cambiarTallas($ajeno, $linea->uuid, [$this->tallas['M'] => 1])] as $accion) {
            try {
                $accion();
                $this->fail('Se esperaba ModelNotFoundException');
            } catch (ModelNotFoundException) {
            }
        }
        $this->assertModelExists($linea);

        $this->editar()->quitar($carrito, $linea->uuid);
        $this->assertSame(1, ImpresionCarrito::count()); // solo la del carrito ajeno
        $this->assertTrue(app(ValorarCarrito::class)($carrito->fresh())->vacio());
    }

    /* ---------- Packs ---------- */

    public function test_un_pack_necesita_diseno_en_sus_zonas_y_el_motor_valida_el_reparto(): void
    {
        $pack = Pack::create(['nombre' => ['es' => 'Pack joven'], 'slug' => ['es' => 'pack-joven'], 'precio' => 8182, 'reparto' => 'libre']);
        $lineaPack = $pack->lineas()->create(['prenda_id' => $this->camiseta->id, 'unidades' => 10, 'colores' => [$this->negro->id],
            'tallas_permitidas' => [$this->tallas['L'], $this->tallas['XL']]]);
        $pack->zonas()->attach($this->zonas['frontal']);
        $dueno = Dueno::sesion('sesion-a');
        $diseno = $this->diseno(['propietario' => 'sesion-a']);
        $bien = [$lineaPack->id => ['color' => $this->negro->id, 'tallas' => [$this->tallas['L'] => 4, $this->tallas['XL'] => 6]]];

        $this->assertProblemas(['faltan_disenos_pack'], fn () => $this->editar()->anadirPack(Carrito::create(), $dueno, $pack->id, $bien, [$this->zonas['espalda'] => $this->impresion($diseno)]));
        $this->assertProblemas(['reparto_pack_no_valido'], fn () => $this->editar()->anadirPack(Carrito::create(), $dueno, $pack->id,
            [$lineaPack->id => ['color' => $this->negro->id, 'tallas' => [$this->tallas['L'] => 4]]], [$this->zonas['frontal'] => $this->impresion($diseno)]));

        $carrito = Carrito::create();
        $this->editar()->anadirPack($carrito, $dueno, $pack->id, $bien, [$this->zonas['frontal'] => $this->impresion($diseno), $this->zonas['espalda'] => $this->impresion($diseno)]);
        $valoracion = app(ValorarCarrito::class)($carrito);
        $this->assertSame(8182 + 10 * 400, $valoracion->resultado->subtotal()); // la espalda se cobra aparte (D106)
        $this->assertTrue($valoracion->confirmable());

        // Una zona extra que no ofrecen todas las prendas del pack no se puede añadir (D106).
        $this->assertProblemas(['zona_no_disponible'], fn () => $this->editar()->anadirPack(Carrito::create(), $dueno, $pack->id, $bien,
            [$this->zonas['frontal'] => $this->impresion($diseno), $this->zonas['manga'] => $this->impresion($diseno)]));

        // Si el backend retira una talla del reparto, la línea lo dice (revisión N3, M1).
        $lineaPack->update(['tallas_permitidas' => [$this->tallas['L']]]);
        $this->assertSame(['reparto_pack_no_valido'], app(ValorarCarrito::class)($carrito->fresh())->lineas[0]['problemas']);
        $lineaPack->update(['tallas_permitidas' => [$this->tallas['L'], $this->tallas['XL']]]);
        Variante::where('talla_id', $this->tallas['XL'])->update(['activa' => false]);
        $this->assertSame(['reparto_pack_no_valido'], app(ValorarCarrito::class)($carrito->fresh())->lineas[0]['problemas']);

        $pack->update(['activo' => false]);
        $this->assertSame(['pack_no_disponible'], app(ValorarCarrito::class)($carrito->fresh())->lineas[0]['problemas']);
    }

    /* ---------- Invitado, entrar en la cuenta y suma de carritos ---------- */

    public function test_el_invitado_tiene_su_carrito_en_una_cookie_y_solo_se_guarda_el_hash(): void
    {
        $carrito = $this->carritoConCamisetas(15, token: hash('sha256', $token = str_repeat('t', 40)));

        $peticion = Request::create('/es', cookies: [CarritoActual::COOKIE => $token]);
        $peticion->setLaravelSession(app('session.store'));
        $this->assertTrue(CarritoActual::de($peticion)?->is($carrito));
        $this->assertNotSame($token, $carrito->fresh()->token);

        $otra = Request::create('/es', cookies: [CarritoActual::COOKIE => str_repeat('x', 40)]);
        $otra->setLaravelSession(app('session.store'));
        $this->assertNull(CarritoActual::de($otra));
    }

    public function test_al_entrar_el_carrito_del_invitado_se_suma_al_suyo_con_sus_disenos(): void
    {
        $usuario = $this->usuario();
        $cliente = $usuario->cliente;
        $suyo = $this->carritoConCamisetas(20, cliente: $cliente->id);
        $repetidoSuyo = $this->diseno(['cliente_id' => $cliente->id, 'sha256' => str_repeat('r', 64)]);

        // El invitado subió un diseño en otra visita (otra sesión) y otro igual al que ya tenía el cliente.
        $deOtraVisita = $this->diseno(['propietario' => 'visita-antigua', 'sha256' => str_repeat('o', 64)]);
        $repetido = $this->diseno(['propietario' => 'visita-antigua', 'sha256' => str_repeat('r', 64)]);
        $invitado = Carrito::create(['token' => hash('sha256', $token = str_repeat('g', 40)), 'cupon' => 'VERANO10']);
        foreach ([$deOtraVisita, $repetido] as $diseno) {
            $this->editar()->anadirPrenda($invitado, Dueno::sesion('visita-antigua'), $this->camiseta->id, $this->negro->id,
                [$this->tallas['L'] => 15], [$this->zonas['frontal'] => $this->impresion($diseno)]);
        }

        $this->withCookie(CarritoActual::COOKIE, $token)
            ->post('/es/cuenta/entrar', ['correo' => $usuario->email, 'contrasena' => self::CLAVE])
            ->assertRedirect('/es/cuenta')->assertCookieExpired(CarritoActual::COOKIE);

        $this->assertModelMissing($invitado);
        $lineas = $suyo->fresh()->lineas()->with('impresiones')->get();
        $this->assertCount(3, $lineas);
        $this->assertSame([20, 15, 15], $lineas->map(fn ($l) => array_sum($l->tallas))->all()); // las suyas primero
        $this->assertSame('VERANO10', $suyo->fresh()->cupon);
        $this->assertSame($cliente->id, $deOtraVisita->fresh()->cliente_id);
        $this->assertModelMissing($repetido);
        $this->assertSame($repetidoSuyo->id, $lineas[2]->impresiones->sole()->diseno_id); // usa el que ya tenía
    }

    public function test_al_sumar_se_queda_el_cupon_del_cliente(): void
    {
        $usuario = $this->usuario();
        Carrito::create(['cliente_id' => $usuario->cliente->id, 'cupon' => 'SUYO']);
        Carrito::create(['token' => hash('sha256', $token = str_repeat('g', 40)), 'cupon' => 'DEL-INVITADO']);

        $this->withCookie(CarritoActual::COOKIE, $token)->post('/es/cuenta/entrar', ['correo' => $usuario->email, 'contrasena' => self::CLAVE]);

        $this->assertSame(['SUYO'], Carrito::pluck('cupon')->all());
    }

    public function test_al_registrarse_el_carrito_del_invitado_pasa_a_su_cuenta(): void
    {
        $invitado = $this->carritoConCamisetas(15, token: hash('sha256', $token = str_repeat('g', 40)));

        $this->withCookie(CarritoActual::COOKIE, $token)->post('/es/cuenta/registro', [
            'nombre' => 'Ana García', 'correo' => 'ana@ejemplo.com', 'contrasena' => self::CLAVE,
            'contrasena_confirmation' => self::CLAVE, 'tipo' => 'particular',
        ])->assertSessionHasNoErrors();

        $cliente = Cliente::where('correo', 'ana@ejemplo.com')->sole();
        $this->assertSame(15, array_sum($cliente->carrito->lineas()->sole()->tallas));
        $this->assertModelMissing($invitado);
    }

    public function test_sumar_dos_veces_no_duplica_y_al_sumar_se_renumera_con_tope(): void
    {
        $usuario = $this->usuario();
        $suyo = Carrito::create(['cliente_id' => $usuario->cliente->id]);
        $invitado = Carrito::create(['token' => hash('sha256', $token = str_repeat('g', 40))]);
        foreach ([[$suyo, 60], [$invitado, 80]] as [$carrito, $n]) {
            for ($i = 0; $i < $n; $i++) {
                $carrito->lineas()->create(['tipo' => 'prenda', 'prenda_id' => $this->camiseta->id, 'color_id' => $this->negro->id, 'tallas' => [$this->tallas['M'] => 1], 'orden' => 1000 + $i]);
            }
        }

        $this->withCookie(CarritoActual::COOKIE, $token)->post('/es/cuenta/entrar', ['correo' => $usuario->email, 'contrasena' => self::CLAVE]);
        $this->assertSame(range(1, FusionarCarrito::TOPE), $suyo->lineas()->pluck('orden')->all());
        $this->assertModelMissing($invitado);

        // Con la cookie vieja ya no hay nada que sumar.
        app(FusionarCarrito::class)(Request::create('/es', cookies: [CarritoActual::COOKIE => $token]), $usuario->cliente);
        $this->assertSame(FusionarCarrito::TOPE, $suyo->lineas()->count());
    }

    public function test_al_verificar_el_correo_en_el_mismo_navegador_se_suma_y_en_otro_no(): void
    {
        $usuario = $this->usuario();
        $usuario->forceFill(['email_verified_at' => null])->save();
        $enlace = fn (User $u) => URL::temporarySignedRoute('cuenta.es.verificar', now()->addHour(), ['id' => $u->id, 'hash' => sha1($u->email)]);
        $invitado = $this->carritoConCamisetas(15, token: hash('sha256', $token = str_repeat('g', 40)));

        // Otra persona con la sesión abierta en ese navegador: no se le suma.
        $otro = User::factory()->create();
        $this->actingAs($otro)->withCookie(CarritoActual::COOKIE, $token)->get($enlace($usuario));
        $this->assertModelExists($invitado);

        $this->flushSession(); // otro navegador: sin la sesión de la otra persona
        $this->app['auth']->forgetGuards();
        $this->actingAs($usuario->fresh())->withCookie(CarritoActual::COOKIE, $token)->get($enlace($usuario->fresh()));
        $this->assertModelMissing($invitado);
        $this->assertSame(1, $usuario->cliente->carrito->lineas()->count());
    }

    public function test_quien_vuelve_con_recordarme_tambien_suma_su_carrito(): void
    {
        $usuario = $this->usuario();
        $usuario->forceFill(['remember_token' => $recuerdo = str_repeat('r', 60)])->save();
        $invitado = $this->carritoConCamisetas(15, token: hash('sha256', $token = str_repeat('g', 40)));
        $guarda = auth()->guard('web');

        $this->withCookie(CarritoActual::COOKIE, $token)
            ->withCookie($guarda->getRecallerName(), $usuario->id.'|'.$recuerdo.'|'.$usuario->password)
            ->get('/es/cuenta')->assertOk();

        $this->assertModelMissing($invitado);
        $this->assertSame(1, $usuario->cliente->carrito->lineas()->count());
    }

    public function test_al_borrar_el_cliente_se_borra_su_carrito(): void
    {
        $usuario = $this->usuario();
        $carrito = $this->carritoConCamisetas(15, cliente: $usuario->cliente->id);

        app(BorrarCliente::class)($usuario->cliente, User::factory()->create());

        $this->assertModelMissing($carrito);
        $this->assertSame(0, LineaCarrito::count() + ImpresionCarrito::count());
    }

    /* ---------- Retención ---------- */

    public function test_los_carritos_sin_usar_se_borran_y_sus_disenos_de_invitado_despues(): void
    {
        Ajuste::guardar('carrito.dias', 10);
        $viejo = $this->carritoConCamisetas(15);
        $reciente = $this->carritoConCamisetas(15, propietario: 'sesion-b');
        $disenoViejo = $viejo->lineas()->sole()->impresiones()->sole()->diseno;
        $disenoReciente = $reciente->lineas()->sole()->impresiones()->sole()->diseno;
        foreach ([$disenoViejo, $disenoReciente] as $d) {
            $d->forceFill(['created_at' => now()->subDays(60)])->saveQuietly(); // más que los días de diseños sin pedido
        }
        Carrito::whereKey($viejo->id)->update(['updated_at' => now()->subDays(11)]);
        Carrito::whereKey($reciente->id)->update(['updated_at' => now()->subDays(9)]);

        $this->assertSame(1, app(LimpiarCarritos::class)());
        app(LimpiarDisenos::class)();

        $this->assertModelMissing($viejo);
        $this->assertModelExists($reciente);
        $this->assertModelMissing($disenoViejo);
        $this->assertModelExists($disenoReciente); // está en un carrito vivo
        $this->artisan('carritos:limpiar')->expectsOutputToContain('Carritos borrados: 0.')->assertSuccessful();
    }

    public function test_usar_el_carrito_alarga_su_plazo_como_mucho_una_vez_al_dia(): void
    {
        $carrito = $this->carritoConCamisetas(15, token: hash('sha256', $token = str_repeat('t', 40)));
        Carrito::whereKey($carrito->id)->update(['updated_at' => now()->subDays(5)]);
        $peticion = Request::create('/es', cookies: [CarritoActual::COOKIE => $token]);

        CarritoActual::de($peticion);
        $this->assertTrue($carrito->fresh()->updated_at->isToday());
        $cookie = collect(Cookie::getQueuedCookies())->first(fn ($c) => $c->getName() === CarritoActual::COOKIE);
        $this->assertEqualsWithDelta(now()->addDays(30)->getTimestamp(), $cookie->getExpiresTime(), 5);

        // Usado hace menos de un día: no se escribe en la base de datos.
        Carrito::whereKey($carrito->id)->update(['updated_at' => $hace = now()->subHours(23)->startOfSecond()]);
        CarritoActual::de($peticion);
        $this->assertEquals($hace, $carrito->fresh()->updated_at);
    }

    public function test_un_color_o_una_zona_en_un_carrito_no_se_pueden_borrar(): void
    {
        $this->carritoConCamisetas(15);
        $this->camiseta->zonas()->detach($this->zonas['frontal']);
        Variante::query()->delete();

        $this->assertTrue(CatalogoPolicy::enUso(ZonaImpresion::find($this->zonas['frontal'])));
        $this->assertTrue(CatalogoPolicy::enUso($this->negro));
        $this->assertFalse(CatalogoPolicy::enUso(ZonaImpresion::find($this->zonas['manga'])));
    }

    /* ---------- Ayudas ---------- */

    private function editar(): EditarCarrito
    {
        return app(EditarCarrito::class);
    }

    /** @param  list<string>  $esperados */
    private function assertProblemas(array $esperados, callable $accion): void
    {
        try {
            $accion();
            $this->fail('Se esperaba LineaNoValida');
        } catch (LineaNoValida $e) {
            $this->assertSame($esperados, $e->problemas);
        }
    }

    private function impresion(Diseno $diseno): array
    {
        return ['diseno' => $diseno->uuid, 'ancho_mm' => 200, 'alto_mm' => 150, 'desde_cuello_mm' => 75, 'desde_centro_mm' => 0];
    }

    private function carritoConCamisetas(int $unidades, ?int $cliente = null, ?string $token = null, string $propietario = 'sesion-a'): Carrito
    {
        $carrito = Carrito::create(['cliente_id' => $cliente, 'token' => $token]);
        $diseno = $this->diseno($cliente ? ['cliente_id' => $cliente] : ['propietario' => $propietario]);
        $dueno = $cliente ? Dueno::cliente($cliente) : Dueno::sesion($propietario);
        $this->editar()->anadirPrenda($carrito, $dueno, $this->camiseta->id, $this->negro->id, [$this->tallas['M'] => $unidades],
            [$this->zonas['frontal'] => $this->impresion($diseno)]);

        return $carrito->fresh();
    }

    /** @return array{0: Prenda, 1: Color} un polo blanco: lo que cambia en la camiseta negra no le afecta */
    private function otraPrenda(): array
    {
        $blanco = Color::factory()->create(['nombre' => ['es' => 'Blanco']]);
        $polo = Prenda::factory()->for(Familia::factory()->create(['nombre' => ['es' => 'Polos']]))->create(['nombre' => ['es' => 'Polo']]);
        Variante::factory()->for($polo)->create(['color_id' => $blanco->id, 'talla_id' => $this->tallas['M'], 'precio_base' => 700, 'peso_gramos' => 210]);
        $polo->zonas()->attach($this->zonas['espalda'], ['precio' => 400, 'coste' => 100]);

        return [$polo, $blanco];
    }

    private function usuario(): User
    {
        $u = User::factory()->create(['email' => 'cliente@ejemplo.com', 'password' => Hash::make(self::CLAVE), 'activo' => true]);
        Cliente::factory()->create(['user_id' => $u->id, 'correo' => $u->email]);

        return $u->fresh();
    }

    private function diseno(array $datos): Diseno
    {
        static $n = 0;
        $n++;

        return Diseno::forceCreate([...[
            'uuid' => sprintf('00000000-0000-0000-0000-%012d', $n), 'cliente_id' => null, 'propietario' => null, 'nombre_original' => "diseno-{$n}.png",
            'ruta' => "x{$n}.png", 'miniatura' => "x{$n}.webp", 'bytes' => 1, 'sha256' => hash('sha256', (string) $n), 'ancho_px' => 3000, 'alto_px' => 3000,
            'util_x' => 0, 'util_y' => 0, 'util_ancho_px' => 3000, 'util_alto_px' => 3000, 'fondo' => 'transparente', 'profundidad_bits' => 8,
            'derechos_declarados_en' => now(), 'derechos_texto' => 'Declaro…',
        ], ...$datos]);
    }
}
