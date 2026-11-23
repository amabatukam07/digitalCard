<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\CatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Blade;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Tests\TestCase;

class ProfessionalBrandingAndUiTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_and_authenticated_pages_use_professional_copy_and_wide_shell(): void
    {
        $this->seed(CatalogSeeder::class);

        $forbiddenPhrases = [
            'Projeto universitário',
            'universitária',
            'Universitário',
            'Cartão fictício',
            'Estudante DigitalCard',
            'para estudo',
            'ambiente acadêmico',
            'Simulação acadêmica',
            'Criar conta de demonstração',
            'Conta Demonstrativa',
            'pedidos demonstrativos',
            'scroll-reveal-card',
        ];

        $publicRoutes = [
            route('catalog.index'),
            route('login'),
            route('register'),
            route('admin.login'),
        ];

        foreach ($publicRoutes as $url) {
            $response = $this->get($url);
            $response->assertOk();

            foreach ($forbiddenPhrases as $phrase) {
                $response->assertDontSee($phrase, false);
            }
        }

        $home = $this->get(route('catalog.index'));
        $home->assertSee('app-shell', false);
        $home->assertDontSee('pagination-scroll-reveal', false);

        $customer = User::factory()->create();
        $cardResponse = $this->actingAs($customer)->get(route('card.show'));
        $cardResponse->assertOk();
        $cardResponse->assertSee('app-shell', false);
        $cardResponse->assertSee('Carteira digital');

        foreach ($forbiddenPhrases as $phrase) {
            $cardResponse->assertDontSee($phrase, false);
        }
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
}
