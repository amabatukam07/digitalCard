<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\User;
use Database\Seeders\CatalogSeeder;
use Database\Seeders\PlanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Blade;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Tests\TestCase;

class ProfessionalBrandingAndUiTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_and_authenticated_pages_use_nexosupri_branding_and_wide_shell(): void
    {
        $this->seed([PlanSeeder::class, CatalogSeeder::class]);

        $forbiddenPhrases = [
            'DigitalCard',
            'digitalcard',
            'cashback',
            'Cashback',
            'Projeto universitário',
            'universitária',
            'Estudante DigitalCard',
            'Simulação acadêmica',
            'scroll-reveal-card',
        ];

        $publicRoutes = [
            route('catalog.index'),
            route('plans.index'),
            route('login'),
            route('register'),
            route('admin.login'),
        ];

        foreach ($publicRoutes as $url) {
            $response = $this->get($url);
            $response->assertOk();
            $response->assertSee('NexoSupri');

            foreach ($forbiddenPhrases as $phrase) {
                $response->assertDontSee($phrase, false);
            }
        }

        $home = $this->get(route('catalog.index'));
        $home->assertSee('app-shell', false);
        $home->assertSee('Cartão Corporativo · Modelo A');
        $home->assertDontSee('pagination-scroll-reveal', false);
        $home->assertDontSee('Insumos por categoria');
        $home->assertDontSee('Diferenciais NexoSupri');
        $home->assertDontSee('Escolha o plano ideal para sua empresa');

        $company = Company::factory()->create(['trade_name' => 'Empresa Modelo UI']);
        $company->wallet()->update(['balance' => 1000.00]);
        $manager = User::factory()->forCompany($company)->create();

        foreach ([route('company.dashboard'), route('wallet.show')] as $authUrl) {
            $resp = $this->actingAs($manager)->get($authUrl);
            $resp->assertOk();
            $resp->assertSee('app-shell', false);

            foreach ($forbiddenPhrases as $phrase) {
                $resp->assertDontSee($phrase, false);
            }
        }

        $this->actingAs($manager)->get(route('wallet.show'))
            ->assertSee('Cartão Corporativo · Modelo A')
            ->assertSee('Regras do Modelo A');

        $this->actingAs($manager)->get('/meu-cartao')
            ->assertRedirect('/carteira');
    }

    public function test_icon_component_respects_explicit_size_classes_without_colliding_size_4(): void
    {
        $moonHtml = Blade::render('<x-icon name="moon" class="size-5" />');
        $this->assertStringContainsString('size-5', $moonHtml);
        $this->assertStringNotContainsString('size-4', $moonHtml);

        $chevronHtml = Blade::render('<x-icon name="chevron-down" class="size-3.5 text-slate-400" />');
        $this->assertStringContainsString('size-3.5', $chevronHtml);
        $this->assertStringNotContainsString('size-4', $chevronHtml);

        $defaultHtml = Blade::render('<x-icon name="truck" class="text-slate-300" />');
        $this->assertStringContainsString('size-4', $defaultHtml);
    }

    public function test_every_referenced_icon_component_has_a_matching_svg_file(): void
    {
        $svgFiles = glob(resource_path('svg/icons/*.svg')) ?: [];
        $availableIcons = array_map(
            static fn (string $file): string => basename($file, '.svg'),
            $svgFiles
        );

        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(resource_path('views')));
        $missing = [];

        foreach ($iterator as $file) {
            if (! $file->isFile() || ! str_ends_with($file->getFilename(), '.blade.php')) {
                continue;
            }

            $content = (string) file_get_contents($file->getPathname());
            if (preg_match_all('/<x-icon[^>]*\bname="([a-z0-9-]+)"/', $content, $matches)) {
                foreach ($matches[1] as $iconName) {
                    if (! in_array($iconName, $availableIcons, true)) {
                        $missing[] = $file->getFilename().' -> '.$iconName;
                    }
                }
            }
        }

        $this->assertSame([], array_values(array_unique($missing)));
    }

    public function test_every_svg_icon_rect_has_explicit_width_and_height(): void
    {
        $svgFiles = glob(resource_path('svg/icons/*.svg')) ?: [];
        $this->assertNotEmpty($svgFiles);

        $broken = [];
        foreach ($svgFiles as $file) {
            $content = (string) file_get_contents($file);
            if (preg_match_all('/<rect\b([^>]*)>/i', $content, $matches)) {
                foreach ($matches[1] as $attrs) {
                    if (! preg_match('/\bwidth="/', $attrs) || ! preg_match('/\bheight="/', $attrs)) {
                        $broken[] = basename($file);
                    }
                }
            }
        }

        $this->assertSame([], array_values(array_unique($broken)));
    }
}
