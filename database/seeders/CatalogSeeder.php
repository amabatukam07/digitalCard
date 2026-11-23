<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Product;
use App\Models\ProductBundle;
use App\Models\ProductBundleItem;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Cache;

class CatalogSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // -------------------------------------------------------------
        // 1. ELETRÔNICOS
        // -------------------------------------------------------------
        $eletronicos = Category::updateOrCreate(
            ['slug' => 'eletronicos'],
            ['name' => 'Eletrônicos', 'parent_id' => null, 'icon' => 'smartphone']
        );

        $smartphones = Category::updateOrCreate(
            ['slug' => 'smartphones'],
            ['name' => 'Smartphones', 'parent_id' => $eletronicos->id, 'icon' => 'smartphone']
        );

        $audio = Category::updateOrCreate(
            ['slug' => 'audio-fones'],
            ['name' => 'Áudio & Fones', 'parent_id' => $eletronicos->id, 'icon' => 'headphones']
        );

        $smartwatches = Category::updateOrCreate(
            ['slug' => 'smartwatches'],
            ['name' => 'Smartwatches & Wearables', 'parent_id' => $eletronicos->id, 'icon' => 'watch']
        );

        $tablets = Category::updateOrCreate(
            ['slug' => 'tablets-ereaders'],
            ['name' => 'Tablets & E-readers', 'parent_id' => $eletronicos->id, 'icon' => 'tablet']
        );

        // -------------------------------------------------------------
        // 2. INFORMÁTICA
        // -------------------------------------------------------------
        $informatica = Category::updateOrCreate(
            ['slug' => 'informatica'],
            ['name' => 'Informática', 'parent_id' => null, 'icon' => 'laptop']
        );

        $notebooks = Category::updateOrCreate(
            ['slug' => 'notebooks'],
            ['name' => 'Notebooks', 'parent_id' => $informatica->id, 'icon' => 'laptop']
        );

        $perifericos = Category::updateOrCreate(
            ['slug' => 'perifericos'],
            ['name' => 'Periféricos', 'parent_id' => $informatica->id, 'icon' => 'mouse']
        );

        $monitores = Category::updateOrCreate(
            ['slug' => 'monitores'],
            ['name' => 'Monitores & Telas', 'parent_id' => $informatica->id, 'icon' => 'monitor']
        );

        $armazenamento = Category::updateOrCreate(
            ['slug' => 'armazenamento'],
            ['name' => 'Armazenamento & Memória', 'parent_id' => $informatica->id, 'icon' => 'hard-drive']
        );

        // -------------------------------------------------------------
        // 3. GAMES
        // -------------------------------------------------------------
        $games = Category::updateOrCreate(
            ['slug' => 'games'],
            ['name' => 'Games', 'parent_id' => null, 'icon' => 'gamepad-2']
        );

        $consoles = Category::updateOrCreate(
            ['slug' => 'consoles'],
            ['name' => 'Consoles', 'parent_id' => $games->id, 'icon' => 'gamepad-2']
        );

        $acessoriosGamer = Category::updateOrCreate(
            ['slug' => 'acessorios-gamer'],
            ['name' => 'Acessórios Gamer', 'parent_id' => $games->id, 'icon' => 'gamepad']
        );

        // -------------------------------------------------------------
        // 4. CASA & COZINHA
        // -------------------------------------------------------------
        $casaCozinha = Category::updateOrCreate(
            ['slug' => 'casa-cozinha'],
            ['name' => 'Casa & Cozinha', 'parent_id' => null, 'icon' => 'cooking-pot']
        );

        $eletrodomesticos = Category::updateOrCreate(
            ['slug' => 'eletrodomesticos'],
            ['name' => 'Eletrodomésticos', 'parent_id' => $casaCozinha->id, 'icon' => 'cooking-pot']
        );

        $assistentesHubs = Category::updateOrCreate(
            ['slug' => 'assistentes-hubs'],
            ['name' => 'Assistentes & Hubs', 'parent_id' => $casaCozinha->id, 'icon' => 'speaker']
        );

        $seguranca = Category::updateOrCreate(
            ['slug' => 'seguranca-conectividade'],
            ['name' => 'Segurança & Iluminação', 'parent_id' => $casaCozinha->id, 'icon' => 'lightbulb']
        );

        // -------------------------------------------------------------
        // 5. CÂMERAS & DRONES
        // -------------------------------------------------------------
        $camerasDrones = Category::updateOrCreate(
            ['slug' => 'cameras-drones'],
            ['name' => 'Câmeras & Drones', 'parent_id' => null, 'icon' => 'camera']
        );

        $camerasAcao = Category::updateOrCreate(
            ['slug' => 'cameras-acao'],
            ['name' => 'Câmeras & Ação', 'parent_id' => $camerasDrones->id, 'icon' => 'camera']
        );

        $drones = Category::updateOrCreate(
            ['slug' => 'drones-estabilizadores'],
            ['name' => 'Drones & Estabilizadores', 'parent_id' => $camerasDrones->id, 'icon' => 'plane']
        );

        // =============================================================
        // PRODUTOS
        // =============================================================
        $products = [
            // --- SMARTPHONES ---
            [
                'category_id' => $smartphones->id,
                'slug' => 'smartphone-galaxy-s24-ultra',
                'name' => 'Smartphone Galaxy S24 Ultra 256GB Titânio',
                'description' => 'Tela Dynamic AMOLED 2X de 6.8", processador Snapdragon 8 Gen 3, câmera quádrupla de até 200MP com zoom óptico de 5x e caneta S Pen inclusa.',
                'price' => 5899.00,
                'compare_at_price' => 6499.00,
                'cashback_percent' => 3.00,
                'stock' => 15,
                'image_path' => '/images/products/placeholder.svg',
            ],
            [
                'category_id' => $smartphones->id,
                'slug' => 'iphone-16-pro-max-256gb',
                'name' => 'Apple iPhone 16 Pro Max 256GB Titânio Natural',
                'description' => 'Design em titânio grau 5 aeroespacial, tela Super Retina XDR de 6.9", potente chip A18 Pro, botão de Controle da Câmera e gravação em 4K Dolby Vision a 120 qps.',
                'price' => 8999.00,
                'compare_at_price' => 9999.00,
                'cashback_percent' => 3.00,
                'stock' => 12,
                'image_path' => '/images/products/placeholder.svg',
            ],
            [
                'category_id' => $smartphones->id,
                'slug' => 'xiaomi-14-ultra-512gb',
                'name' => 'Xiaomi 14 Ultra 512GB Leica Optical Quad Camera',
                'description' => 'Engenharia óptica Leica com sensor Sony LYT-900 de 1 polegada, abertura variável contínua, Snapdragon 8 Gen 3 e carregador HyperCharge de 90W.',
                'price' => 5499.00,
                'compare_at_price' => 6199.00,
                'cashback_percent' => 4.00,
                'stock' => 10,
                'image_path' => '/images/products/placeholder.svg',
            ],
            [
                'category_id' => $smartphones->id,
                'slug' => 'google-pixel-9-pro-128gb',
                'name' => 'Google Pixel 9 Pro 128GB Porcelain com Gemini AI',
                'description' => 'O ápice da inteligência artificial móvel com processador Google Tensor G4, recursos proeminentes do Gemini integrados e sistema fotográfico triplo de nível profissional.',
                'price' => 6299.00,
                'compare_at_price' => 6999.00,
                'cashback_percent' => 3.00,
                'stock' => 8,
                'image_path' => '/images/products/placeholder.svg',
            ],

            // --- ÁUDIO & FONES ---
            [
                'category_id' => $audio->id,
                'slug' => 'headphone-sony-wh-1000xm5-bluetooth',
                'name' => 'Headphone Sony WH-1000XM5 com Cancelamento de Ruído',
                'description' => 'Líder em cancelamento de ruído ativo com dois processadores e 8 microfones, áudio Hi-Res sem fio, chamadas ultra nítidas e bateria com duração de até 30 horas.',
                'price' => 1899.00,
                'compare_at_price' => 2199.00,
                'cashback_percent' => 5.00,
                'stock' => 25,
                'image_path' => '/images/products/placeholder.svg',
            ],
            [
                'category_id' => $audio->id,
                'slug' => 'apple-airpods-pro-2-usbc',
                'name' => 'Apple AirPods Pro (2ª Geração) com Estojo MagSafe USB-C',
                'description' => 'Cancelamento Ativo de Ruído até 2x mais eficiente, Áudio Espacial Personalizado com rastreamento dinâmico da cabeça e estojo com alto-falante integrado para Busca Precisa.',
                'price' => 1649.00,
                'compare_at_price' => 1999.00,
                'cashback_percent' => 4.00,
                'stock' => 30,
                'image_path' => '/images/products/placeholder.svg',
            ],
            [
                'category_id' => $audio->id,
                'slug' => 'jbl-charge-5-bluetooth',
                'name' => 'Caixa de Som Portátil JBL Charge 5 Bluetooth IP67',
                'description' => 'Potente JBL Original Pro Sound com driver de longa excursão e tweeter separado, bateria para até 20 horas de reprodução contínua e recurso powerbank integrado.',
                'price' => 899.00,
                'compare_at_price' => 1099.00,
                'cashback_percent' => 4.00,
                'stock' => 35,
                'image_path' => '/images/products/placeholder.svg',
            ],
            [
                'category_id' => $audio->id,
                'slug' => 'headphone-bose-quietcomfort-ultra',
                'name' => 'Headphone Bose QuietComfort Ultra com Áudio Imersivo',
                'description' => 'Tecnologia inovadora Bose Immersive Audio, cancelamento de ruído estelar com calibração CustomTune que adapta o som ao formato de cada ouvido.',
                'price' => 2499.00,
                'compare_at_price' => 2899.00,
                'cashback_percent' => 5.00,
                'stock' => 14,
                'image_path' => '/images/products/placeholder.svg',
            ],

            // --- SMARTWATCHES & WEARABLES ---
            [
                'category_id' => $smartwatches->id,
                'slug' => 'apple-watch-series-10-gps-46mm',
                'name' => 'Apple Watch Series 10 GPS 46mm Alumínio Meia-Noite',
                'description' => 'Tela inovadora OLED grande de ângulo ampliado mais brilhante, perfil 10% mais fino, notificações de apneia do sono, ECG e monitoramento avançado de saúde e treinos.',
                'price' => 4199.00,
                'compare_at_price' => 4699.00,
                'cashback_percent' => 3.00,
                'stock' => 18,
                'image_path' => '/images/products/placeholder.svg',
            ],
            [
                'category_id' => $smartwatches->id,
                'slug' => 'samsung-galaxy-watch-ultra-47mm',
                'name' => 'Samsung Galaxy Watch Ultra 47mm LTE Titânio Cinza',
                'description' => 'Caixa de titânio de grau aeroespacial, resistência à água 10ATM / IP68, botão físico de ação rápida e bateria com duração de até 100 horas para esportes extremos.',
                'price' => 3899.00,
                'compare_at_price' => 4399.00,
                'cashback_percent' => 3.00,
                'stock' => 15,
                'image_path' => '/images/products/placeholder.svg',
            ],
            [
                'category_id' => $smartwatches->id,
                'slug' => 'garmin-fenix-7-pro-solar',
                'name' => 'Garmin Fenix 7 Pro Solar Multisport GPS com Lanterna LED',
                'description' => 'Lente de carregamento solar Power Glass para semanas de autonomia, lanterna LED multi-modo integrada, métricas avançadas de treino e mapas TopoActive integrados.',
                'price' => 5499.00,
                'compare_at_price' => 6199.00,
                'cashback_percent' => 4.00,
                'stock' => 10,
                'image_path' => '/images/products/placeholder.svg',
            ],

            // --- TABLETS & E-READERS ---
            [
                'category_id' => $tablets->id,
                'slug' => 'ipad-air-11-m2-128gb',
                'name' => 'Apple iPad Air 11" Chip M2 Wi-Fi 128GB Azul',
                'description' => 'Desempenho impressionante com o processador Apple M2, tela Liquid Retina brilhante antirreflexo com True Tone, suporte a Apple Pencil Pro e Magic Keyboard.',
                'price' => 5299.00,
                'compare_at_price' => 5899.00,
                'cashback_percent' => 3.00,
                'stock' => 16,
                'image_path' => '/images/products/placeholder.svg',
            ],
            [
                'category_id' => $tablets->id,
                'slug' => 'kindle-paperwhite-16gb-luz-ajustavel',
                'name' => 'Kindle Paperwhite 16GB Tela 6.8" com Luz Quente Ajustável',
                'description' => 'Tela antirreflexo de 300 ppi que reproduz texto impresso com perfeição, temperatura de luz ajustável do branco ao âmbar, bateria para até 10 semanas e proteção IPX8 à prova d\'água.',
                'price' => 699.00,
                'compare_at_price' => 799.00,
                'cashback_percent' => 5.00,
                'stock' => 40,
                'image_path' => '/images/products/placeholder.svg',
            ],
            [
                'category_id' => $tablets->id,
                'slug' => 'samsung-galaxy-tab-s9-fe-128gb',
                'name' => 'Samsung Galaxy Tab S9 FE 10.9" Wi-Fi 128GB com Caneta S Pen',
                'description' => 'Tela imersiva de 90Hz, certificação IP68 de resistência contra água e poeira no tablet e na caneta S Pen inclusa, perfeito para estudos, ilustrações e anotações.',
                'price' => 2499.00,
                'compare_at_price' => 2899.00,
                'cashback_percent' => 4.00,
                'stock' => 20,
                'image_path' => '/images/products/placeholder.svg',
            ],

            // --- NOTEBOOKS & MACBOOKS ---
            [
                'category_id' => $notebooks->id,
                'slug' => 'notebook-dell-inspiron-15',
                'name' => 'Notebook Dell Inspiron 15 Core i7 16GB SSD 512GB',
                'description' => 'Alta performance para produtividade e multitarefa com processador Intel Core i7 de 13ª geração, tela Full HD antirreflexo e teclado numérico integrado.',
                'price' => 4199.90,
                'compare_at_price' => 4799.00,
                'cashback_percent' => 2.00,
                'stock' => 10,
                'image_path' => '/images/products/placeholder.svg',
            ],
            [
                'category_id' => $notebooks->id,
                'slug' => 'macbook-air-13-m3-16gb-512gb',
                'name' => 'Apple MacBook Air 13" Chip M3 16GB RAM SSD 512GB Meia-Noite',
                'description' => 'Incrivelmente fino com apenas 11,3mm de espessura, chip Apple M3 com CPU de 8 núcleos e GPU de 10 núcleos, até 18 horas de bateria e tela Liquid Retina de alta precisão.',
                'price' => 9499.00,
                'compare_at_price' => 10499.00,
                'cashback_percent' => 3.00,
                'stock' => 12,
                'image_path' => '/images/products/placeholder.svg',
            ],
            [
                'category_id' => $notebooks->id,
                'slug' => 'notebook-gamer-asus-rog-zephyrus-g16',
                'name' => 'Notebook Gamer ASUS ROG Zephyrus G16 Core Ultra 9 RTX 4070',
                'description' => 'Chassi ultrafino em peça única de alumínio CNC, tela ROG Nebula OLED 2.5K de 240Hz, processador Intel Core Ultra 9 com IA dedicada e placa gráfica NVIDIA RTX 4070.',
                'price' => 13999.00,
                'compare_at_price' => 15499.00,
                'cashback_percent' => 2.00,
                'stock' => 6,
                'image_path' => '/images/products/placeholder.svg',
            ],
            [
                'category_id' => $notebooks->id,
                'slug' => 'notebook-lenovo-thinkpad-e14-i5',
                'name' => 'Notebook Lenovo ThinkPad E14 Intel Core i5 16GB SSD 512GB',
                'description' => 'Confiabilidade e durabilidade de nível militar MIL-SPEC comprovada, teclado ergonômico premiado com TrackPoint, leitor biométrico e tampa mecânica ThinkShutter.',
                'price' => 3899.00,
                'compare_at_price' => 4399.00,
                'cashback_percent' => 3.00,
                'stock' => 15,
                'image_path' => '/images/products/placeholder.svg',
            ],

            // --- PERIFÉRICOS & ACESSÓRIOS ---
            [
                'category_id' => $perifericos->id,
                'slug' => 'teclado-mecanico-logitech-mx-mechanical',
                'name' => 'Teclado Mecânico Sem Fio Logitech MX Mechanical',
                'description' => 'Teclas mecânicas de perfil baixo silenciosas (Tactile Quiet), retroiluminação inteligente com sensor de aproximação das mãos e conexão sem fio Logi Bolt ou Bluetooth.',
                'price' => 749.50,
                'compare_at_price' => 849.00,
                'cashback_percent' => 5.00,
                'stock' => 30,
                'image_path' => '/images/products/placeholder.svg',
            ],
            [
                'category_id' => $perifericos->id,
                'slug' => 'mouse-ergonomico-logitech-mx-master-3s',
                'name' => 'Mouse Sem Fio Logitech MX Master 3S Ergonômico',
                'description' => 'Sensor de alta precisão 8.000 DPI com rastreamento em qualquer superfície (inclusive vidro), rolagem eletromagnética MagSpeed e cliques silenciosos Quiet Clicks.',
                'price' => 589.00,
                'compare_at_price' => 699.00,
                'cashback_percent' => 4.00,
                'stock' => 40,
                'image_path' => '/images/products/placeholder.svg',
            ],
            [
                'category_id' => $perifericos->id,
                'slug' => 'mouse-gamer-razer-deathadder-v3-pro',
                'name' => 'Mouse Gamer Sem Fio Razer DeathAdder V3 Pro Ultralight 63g',
                'description' => 'Formato ergonômico refinado em colaboração com atletas mundiais de eSports, sensor óptico Focus Pro 30K de máxima precisão e switches ópticos de 3ª geração.',
                'price' => 899.00,
                'compare_at_price' => 1049.00,
                'cashback_percent' => 4.00,
                'stock' => 22,
                'image_path' => '/images/products/placeholder.svg',
            ],
            [
                'category_id' => $perifericos->id,
                'slug' => 'teclado-mecanico-corsair-k70-rgb-pro',
                'name' => 'Teclado Mecânico Gamer Corsair K70 RGB PRO Cherry MX Red',
                'description' => 'Chassi de alumínio escovado com switches lineares Cherry MX Red, tecnologia Corsair AXON com frequência de sondagem de 8.000Hz e iluminação RGB dinâmica por tecla.',
                'price' => 999.00,
                'compare_at_price' => 1199.00,
                'cashback_percent' => 4.00,
                'stock' => 18,
                'image_path' => '/images/products/placeholder.svg',
            ],
            [
                'category_id' => $perifericos->id,
                'slug' => 'webcam-logitech-brio-4k-hdr',
                'name' => 'Webcam Ultra HD 4K Logitech Brio com HDR e RightLight 3',
                'description' => 'Transmissão em vídeo cristalino 4K a 30fps ou 1080p a 60fps com HDR, suporte a reconhecimento facial seguro Windows Hello e microfones estéreo integrados antirruído.',
                'price' => 1199.00,
                'compare_at_price' => 1399.00,
                'cashback_percent' => 3.00,
                'stock' => 25,
                'image_path' => '/images/products/placeholder.svg',
            ],

            // --- MONITORES & TELAS ---
            [
                'category_id' => $monitores->id,
                'slug' => 'monitor-gamer-lg-ultragear-27-144hz',
                'name' => 'Monitor Gamer LG UltraGear 27" Full HD IPS 144Hz 1ms',
                'description' => 'Cores precisas com painel IPS e 99% da gama sRGB, taxa de atualização veloz de 144Hz com tempo de resposta real de 1ms MBR, compatível com NVIDIA G-Sync e HDR10.',
                'price' => 1399.00,
                'compare_at_price' => 1599.00,
                'cashback_percent' => 4.00,
                'stock' => 20,
                'image_path' => '/images/products/placeholder.svg',
            ],
            [
                'category_id' => $monitores->id,
                'slug' => 'monitor-dell-ultrasharp-32-4k-usb-c',
                'name' => 'Monitor Profissional Dell UltraSharp 32" 4K Hub USB-C IPS Black',
                'description' => 'Tecnologia IPS Black pioneira com taxa de contraste de 2.000:1, resolução 4K UHD 3840x2160, hub USB-C com fornecimento de até 90W e porta RJ45 Gigabit integrada.',
                'price' => 4799.00,
                'compare_at_price' => 5299.00,
                'cashback_percent' => 3.00,
                'stock' => 8,
                'image_path' => '/images/products/placeholder.svg',
            ],
            [
                'category_id' => $monitores->id,
                'slug' => 'monitor-curvo-samsung-odyssey-oled-g9-49',
                'name' => 'Monitor Curvo Gamer Samsung Odyssey OLED G9 49" 240Hz 0.03ms',
                'description' => 'Tela curva ultrawide 32:9 de 49 polegadas Dual QHD (5120x1440), painel OLED com pretos infinitos, tempo de resposta relâmpago de 0.03ms e processador Neo Quantum Pro.',
                'price' => 9899.00,
                'compare_at_price' => 10999.00,
                'cashback_percent' => 2.00,
                'stock' => 5,
                'image_path' => '/images/products/placeholder.svg',
            ],

            // --- ARMAZENAMENTO & MEMÓRIA ---
            [
                'category_id' => $armazenamento->id,
                'slug' => 'ssd-kingston-kc3000-2tb-nvme',
                'name' => 'SSD Kingston KC3000 2TB NVMe PCIe 4.0 M.2 7000MB/s',
                'description' => 'Velocidades de leitura e gravação fulminantes de até 7.000 MB/s, controlador Phison E18 de alto rendimento e dissipador térmico em alumínio e grafeno de perfil baixo.',
                'price' => 1149.00,
                'compare_at_price' => 1299.00,
                'cashback_percent' => 5.00,
                'stock' => 30,
                'image_path' => '/images/products/placeholder.svg',
            ],
            [
                'category_id' => $armazenamento->id,
                'slug' => 'ssd-portatil-sandisk-extreme-1tb',
                'name' => 'SSD Portátil SanDisk Extreme 1TB USB 3.2 Gen 2 Resistente IP55',
                'description' => 'Velocidades de transferência de até 1.050 MB/s em design compacto e robusto com alça mosquetão, resistência contra quedas de até 2 metros e proteção IP55 contra chuva e poeira.',
                'price' => 689.00,
                'compare_at_price' => 799.00,
                'cashback_percent' => 5.00,
                'stock' => 35,
                'image_path' => '/images/products/placeholder.svg',
            ],

            // --- CONSOLES DE VIDEOGAME ---
            [
                'category_id' => $consoles->id,
                'slug' => 'playstation-5-slim-1tb',
                'name' => 'Console PlayStation 5 Slim Edição Digital 1TB SSD',
                'description' => 'Chassi refinado e mais compacto, armazenamento ultrarrápido SSD de 1TB, feedback tátil imersivo, gatilhos adaptáveis no DualSense e suporte a Ray Tracing em 4K nativo.',
                'price' => 3599.00,
                'compare_at_price' => 3999.00,
                'cashback_percent' => 3.00,
                'stock' => 20,
                'image_path' => '/images/products/placeholder.svg',
            ],
            [
                'category_id' => $consoles->id,
                'slug' => 'xbox-series-x-1tb',
                'name' => 'Console Xbox Series X 1TB Preto Fosco 12 Teraflops',
                'description' => 'O console mais veloz e potente da história do Xbox com 12 teraflops de potência gráfica RDNA 2, Quick Resume para alternar jogos instantaneamente e suporte a 120 FPS.',
                'price' => 4299.00,
                'compare_at_price' => 4799.00,
                'cashback_percent' => 3.00,
                'stock' => 15,
                'image_path' => '/images/products/placeholder.svg',
            ],
            [
                'category_id' => $consoles->id,
                'slug' => 'nintendo-switch-oled-branco',
                'name' => 'Console Nintendo Switch Modelo OLED Branco 64GB',
                'description' => 'Brilhante tela OLED de 7 polegadas com cores intensas, suporte traseiro ajustável largo de mesa, base com entrada LAN integrada e versatilidade única portátil e na TV.',
                'price' => 2099.00,
                'compare_at_price' => 2399.00,
                'cashback_percent' => 4.00,
                'stock' => 25,
                'image_path' => '/images/products/placeholder.svg',
            ],

            // --- ACESSÓRIOS GAMER ---
            [
                'category_id' => $acessoriosGamer->id,
                'slug' => 'controle-sony-dualsense-edge-ps5',
                'name' => 'Controle Sem Fio Sony DualSense Edge PS5 Pro Personalizável',
                'description' => 'Controle de alto desempenho para eSports com módulos analógicos substituíveis, botões traseiros mapeáveis, travas de gatilho ajustáveis e estojo rígido de transporte.',
                'price' => 1349.00,
                'compare_at_price' => 1599.00,
                'cashback_percent' => 4.00,
                'stock' => 18,
                'image_path' => '/images/products/placeholder.svg',
            ],
            [
                'category_id' => $acessoriosGamer->id,
                'slug' => 'headset-hyperx-cloud-iii-wireless',
                'name' => 'Headset Sem Fio Gamer HyperX Cloud III Wireless 120h',
                'description' => 'Drivers angulados de 53mm com calibração acústica profissional, áudio espacial DTS Headphone:X, microfone cristalino de 10mm com LED e bateria absurda para até 120 horas de gameplay.',
                'price' => 899.00,
                'compare_at_price' => 1099.00,
                'cashback_percent' => 5.00,
                'stock' => 24,
                'image_path' => '/images/products/placeholder.svg',
            ],
            [
                'category_id' => $acessoriosGamer->id,
                'slug' => 'volante-logitech-g29-driving-force',
                'name' => 'Volante e Pedais Logitech G29 Driving Force Force Feedback',
                'description' => 'Force Feedback realista de motor duplo com engrenagens helicoidais anti-retrocesso, 900 graus de rotação volante-a-volante e pedais responsivos não lineares em aço escovado.',
                'price' => 1799.00,
                'compare_at_price' => 2099.00,
                'cashback_percent' => 4.00,
                'stock' => 12,
                'image_path' => '/images/products/placeholder.svg',
            ],

            // --- CASA & COZINHA (ELETRODOMÉSTICOS & CASA INTELIGENTE) ---
            [
                'category_id' => $eletrodomesticos->id,
                'slug' => 'air-fryer-philips-walita-essential-xl',
                'name' => 'Air Fryer Philips Walita Essential XL 6,2L Digital',
                'description' => 'Fritadeira sem óleo com capacidade de 6,2 litros, tecnologia Rapid Air para refeições crocantes por fora e suculentas por dentro, com 7 receitas pré-programadas.',
                'price' => 699.00,
                'compare_at_price' => 899.00,
                'cashback_percent' => 6.00,
                'stock' => 30,
                'image_path' => '/images/products/placeholder.svg',
            ],
            [
                'category_id' => $eletrodomesticos->id,
                'slug' => 'cafeteira-nespresso-vertuo-pop',
                'name' => 'Cafeteira Nespresso Vertuo Pop Preto Espresso e Café',
                'description' => 'Sistema de extração por centrifugação que prepara cafés de 40ml a 230ml com crema perfeita, aquecimento em apenas 30 segundos e design compacto para qualquer cozinha.',
                'price' => 899.00,
                'compare_at_price' => 1099.00,
                'cashback_percent' => 5.00,
                'stock' => 22,
                'image_path' => '/images/products/placeholder.svg',
            ],
            [
                'category_id' => $assistentesHubs->id,
                'slug' => 'amazon-echo-dot-5-alexa',
                'name' => 'Smart Speaker Amazon Echo Dot 5ª Geração com Alexa',
                'description' => 'Experiência de áudio aprimorada com vocais nítidos e graves profundos, sensores integrados de movimento e temperatura ambiente e controle completo de rotinas por voz.',
                'price' => 389.00,
                'compare_at_price' => 449.00,
                'cashback_percent' => 5.00,
                'stock' => 50,
                'image_path' => '/images/products/placeholder.svg',
            ],
            [
                'category_id' => $assistentesHubs->id,
                'slug' => 'amazon-echo-show-8-3-geracao',
                'name' => 'Smart Display Amazon Echo Show 8 (3ª Geração) Áudio Espacial',
                'description' => 'Tela HD de 8 polegadas sensível ao toque com som espacial envolvente, câmera central de 13 MP com enquadramento automático para chamadas em vídeo e hub Zigbee/Matter integrado.',
                'price' => 1199.00,
                'compare_at_price' => 1399.00,
                'cashback_percent' => 4.00,
                'stock' => 20,
                'image_path' => '/images/products/placeholder.svg',
            ],
            [
                'category_id' => $seguranca->id,
                'slug' => 'camera-intelbras-im3-c-wifi',
                'name' => 'Câmera de Segurança Inteligente Intelbras Wi-Fi Full HD iM3 C',
                'description' => 'Resolução Full HD 1080p com campo de visão superamplo de 131°, visão noturna de até 10 metros, detecção inteligente de ruídos e interação com áudio bidirecional.',
                'price' => 249.00,
                'compare_at_price' => 299.00,
                'cashback_percent' => 5.00,
                'stock' => 40,
                'image_path' => '/images/products/placeholder.svg',
            ],
            [
                'category_id' => $seguranca->id,
                'slug' => 'fechadura-digital-intelbras-fr101',
                'name' => 'Fechadura Digital de Sobrepor Intelbras FR 101 com Teclado Touch',
                'description' => 'Abertura rápida e segura via senha de 4 a 12 dígitos, sensor de travamento automático ao bater a porta, alarme sonoro contra arrombamento e função não perturbe.',
                'price' => 749.00,
                'compare_at_price' => 849.00,
                'cashback_percent' => 4.00,
                'stock' => 15,
                'image_path' => '/images/products/placeholder.svg',
            ],
            [
                'category_id' => $seguranca->id,
                'slug' => 'lampada-inteligente-philips-smart-led',
                'name' => 'Lâmpada Inteligente Wi-Fi Philips Smart LED Colors E27 9W',
                'description' => 'Escolha entre 16 milhões de cores vibrantes e regule a temperatura do branco quente ao frio, programação de horários automáticos e integração com Alexa e Google Assistente.',
                'price' => 99.00,
                'compare_at_price' => 129.00,
                'cashback_percent' => 5.00,
                'stock' => 60,
                'image_path' => '/images/products/placeholder.svg',
            ],

            // --- CÂMERAS & DRONES ---
            [
                'category_id' => $camerasAcao->id,
                'slug' => 'camera-sony-alpha-a7-iv',
                'name' => 'Câmera Mirrorless Full-Frame Sony Alpha A7 IV 4K 33MP (Corpo)',
                'description' => 'Sensor Full-Frame Exmor R de 33MP, processador de última geração BIONZ XR, gravação 4K 60p em 10-bit 4:2:2 e autofoco híbrido em tempo real para pessoas, aves e animais.',
                'price' => 14899.00,
                'compare_at_price' => 16499.00,
                'cashback_percent' => 2.00,
                'stock' => 5,
                'image_path' => '/images/products/placeholder.svg',
            ],
            [
                'category_id' => $camerasAcao->id,
                'slug' => 'camera-gopro-hero-12-black',
                'name' => 'Câmera de Ação GoPro HERO12 Black À Prova D\'Água 5.3K',
                'description' => 'Vídeos impressionantes em 5.3K60 e 4K120, estabilização de vídeo premiada HyperSmooth 6.0 com trava de horizonte 360°, fotos de 27MP e resistente à água até 10 metros sem case.',
                'price' => 2499.00,
                'compare_at_price' => 2899.00,
                'cashback_percent' => 4.00,
                'stock' => 18,
                'image_path' => '/images/products/placeholder.svg',
            ],
            [
                'category_id' => $drones->id,
                'slug' => 'drone-dji-mini-4-pro-rc2',
                'name' => 'Drone DJI Mini 4 Pro com Controle DJI RC 2 Tela Integrada',
                'description' => 'Design ultraleve dobrável com menos de 249g, gravação 4K/60fps HDR, detecção omnidirecional de obstáculos em tempo real e tempo máximo de voo de até 34 minutos.',
                'price' => 7999.00,
                'compare_at_price' => 8899.00,
                'cashback_percent' => 3.00,
                'stock' => 7,
                'image_path' => '/images/products/placeholder.svg',
            ],
            [
                'category_id' => $drones->id,
                'slug' => 'estabilizador-dji-osmo-mobile-6',
                'name' => 'Estabilizador Gimbal de Smartphone DJI Osmo Mobile 6',
                'description' => 'Estabilização motorizada de 3 eixos, desdobramento magnético ultrarrápido, haste extensora embutida para selfies e ângulos dinâmicos, com rastreamento inteligente ActiveTrack 6.0.',
                'price' => 999.00,
                'compare_at_price' => 1199.00,
                'cashback_percent' => 4.00,
                'stock' => 20,
                'image_path' => '/images/products/placeholder.svg',
            ],
        ];

        $escritorio = Category::updateOrCreate(
            ['slug' => 'escritorio-ergonomia'],
            ['name' => 'Escritório & Ergonomia', 'parent_id' => null, 'icon' => 'armchair']
        );
        $cadeirasErgonomicas = Category::updateOrCreate(
            ['slug' => 'cadeiras-ergonomicas'],
            ['name' => 'Cadeiras Ergonômicas', 'parent_id' => $escritorio->id, 'icon' => 'armchair']
        );
        $mesasIluminacao = Category::updateOrCreate(
            ['slug' => 'mesas-iluminacao-setup'],
            ['name' => 'Mesas & Iluminação', 'parent_id' => $escritorio->id, 'icon' => 'lightbulb']
        );

        $audioPro = Category::updateOrCreate(
            ['slug' => 'audio-pro-criacao'],
            ['name' => 'Áudio Pro & Criação', 'parent_id' => null, 'icon' => 'mic']
        );
        $microfonesInterfaces = Category::updateOrCreate(
            ['slug' => 'microfones-interfaces'],
            ['name' => 'Microfones & Interfaces', 'parent_id' => $audioPro->id, 'icon' => 'mic']
        );
        $streamingAcessorios = Category::updateOrCreate(
            ['slug' => 'streaming-acessorios'],
            ['name' => 'Streaming & Studio', 'parent_id' => $audioPro->id, 'icon' => 'sliders-horizontal']
        );

        $mobilidadeEnergia = Category::updateOrCreate(
            ['slug' => 'mobilidade-energia'],
            ['name' => 'Mobilidade & Energia', 'parent_id' => null, 'icon' => 'battery-charging']
        );
        $powerBanksCarregadores = Category::updateOrCreate(
            ['slug' => 'power-banks-carregadores'],
            ['name' => 'Carregadores & Energia', 'parent_id' => $mobilidadeEnergia->id, 'icon' => 'battery-charging']
        );
        $mochilasCases = Category::updateOrCreate(
            ['slug' => 'mochilas-cases'],
            ['name' => 'Mochilas & Proteção', 'parent_id' => $mobilidadeEnergia->id, 'icon' => 'package']
        );

        $extraProducts = [
            // --- ESCRITÓRIO & ERGONOMIA ---
            [
                'category_id' => $cadeirasErgonomicas->id,
                'slug' => 'cadeira-ergonomica-herman-miller-aeron',
                'name' => 'Cadeira Ergonômica Herman Miller Aeron Grafite (Tamanho B)',
                'description' => 'Referência mundial em ergonomia corporativa com tela Pellicle respirável de 8 zonas de tensão, suporte lombar PostureFit SL e mecanismo Harmonic 2 de inclinação equilibrada.',
                'price' => 6890.00,
                'compare_at_price' => 7990.00,
                'cashback_percent' => 6.00,
                'stock' => 6,
            ],
            [
                'category_id' => $cadeirasErgonomicas->id,
                'slug' => 'cadeira-ergonomica-elements-astra-pro',
                'name' => 'Cadeira Ergonômica Elements Astra Pro em Mesh com Apoio de Cabeça',
                'description' => 'Encosto em tela mesh de alta elasticidade, ajuste lombar independente, braços 3D com acabamento macio e base em alumínio reforçado para longas jornadas de trabalho.',
                'price' => 1899.00,
                'compare_at_price' => 2299.00,
                'cashback_percent' => 8.00,
                'stock' => 22,
            ],
            [
                'category_id' => $mesasIluminacao->id,
                'slug' => 'mesa-regulavel-eletrica-geniodesks-pro',
                'name' => 'Mesa com Regulagem Elétrica de Altura GenioDesks Pro Nogueira',
                'description' => 'Estrutura com motor duplo silencioso, controlador digital com 4 memórias de altura (62cm a 127cm), tampo amplo em nogueira fosca e calha organizadora de cabos.',
                'price' => 2890.00,
                'compare_at_price' => 3490.00,
                'cashback_percent' => 7.00,
                'stock' => 11,
            ],
            [
                'category_id' => $mesasIluminacao->id,
                'slug' => 'luminaria-monitor-benq-screenbar-halo',
                'name' => 'Luminária de Monitor BenQ ScreenBar Halo com Controle Sem Fio',
                'description' => 'Iluminação assimétrica sem reflexos na tela, retroiluminação ambiente traseira para conforto ocular, sensor automático de luz e disco giratório wireless em alumínio.',
                'price' => 1190.00,
                'compare_at_price' => 1390.00,
                'cashback_percent' => 8.00,
                'stock' => 19,
            ],
            [
                'category_id' => $mesasIluminacao->id,
                'slug' => 'suporte-articulado-duplo-elg-f160n',
                'name' => 'Suporte Articulado Duplo com Pistão a Gás ELG F160N (17" a 35")',
                'description' => 'Braços articulados independentes com pistão a gás, rotação 360° retrato/paisagem, suporte VESA 75x75/100x100mm e passa-cabos integrado para setup limpo.',
                'price' => 349.00,
                'compare_at_price' => 449.00,
                'cashback_percent' => 9.00,
                'stock' => 45,
            ],
            [
                'category_id' => $mesasIluminacao->id,
                'slug' => 'dock-station-anker-575-usbc-13em1',
                'name' => 'Dock Station Anker 575 USB-C 13-em-1 com Power Delivery 85W',
                'description' => 'Centralize até 3 monitores (HDMI duplo 4K + DisplayPort), Gigabit Ethernet, leitor SD/microSD UHS-I e carregamento rápido de até 85W no notebook via único cabo USB-C.',
                'price' => 1490.00,
                'compare_at_price' => 1790.00,
                'cashback_percent' => 7.00,
                'stock' => 17,
            ],

            // --- ÁUDIO PRO & CRIAÇÃO ---
            [
                'category_id' => $microfonesInterfaces->id,
                'slug' => 'microfone-dinamico-shure-sm7db',
                'name' => 'Microfone Dinâmico Broadcast Shure SM7dB com Pré-Amplificador Ativo',
                'description' => 'Lendária cápsula cardióide SM7B agora com pré-amplificador embutido de até +28dB desenhado com a Shure, chave bypass e filtros de presença para vocais e podcasts.',
                'price' => 3790.00,
                'compare_at_price' => 4290.00,
                'cashback_percent' => 5.00,
                'stock' => 9,
            ],
            [
                'category_id' => $microfonesInterfaces->id,
                'slug' => 'microfone-usb-hyperx-quadcast-s',
                'name' => 'Microfone Condensador USB HyperX QuadCast S RGB com Shock Mount',
                'description' => 'Quatro padrões polares selecionáveis (estéreo, omnidirecional, cardióide e bidirecional), sensor toque-para-silenciar com LED indicativo, filtro pop interno e suporte antivibração.',
                'price' => 849.00,
                'compare_at_price' => 1049.00,
                'cashback_percent' => 10.00,
                'stock' => 32,
            ],
            [
                'category_id' => $microfonesInterfaces->id,
                'slug' => 'interface-audio-focusrite-scarlett-2i2-4g',
                'name' => 'Interface de Áudio Focusrite Scarlett 2i2 4ª Geração USB-C',
                'description' => 'Dois pré-amplificadores de baixíssimo ruído com 69dB de ganho, faixa dinâmica de 120dB, modos Auto Gain e Clip Safe automáticos e circuito Air analógico atualizado.',
                'price' => 1590.00,
                'compare_at_price' => 1890.00,
                'cashback_percent' => 6.00,
                'stock' => 18,
            ],
            [
                'category_id' => $streamingAcessorios->id,
                'slug' => 'elgato-stream-deck-mk2-15-teclas',
                'name' => 'Controlador Elgato Stream Deck MK.2 com 15 Teclas LCD Personalizáveis',
                'description' => 'Automação instantânea de fluxos de trabalho, atalhos de edição, cenas do OBS, áudio e iluminação com 15 teclas LCD dinâmicas, cabo USB-C removível e suporte inclinado.',
                'price' => 1149.00,
                'compare_at_price' => 1349.00,
                'cashback_percent' => 8.00,
                'stock' => 24,
            ],
            [
                'category_id' => $streamingAcessorios->id,
                'slug' => 'placa-captura-elgato-hd60-x-4k60',
                'name' => 'Placa de Captura Externa Elgato HD60 X 4K60 HDR10 com VRR Passthrough',
                'description' => 'Capture gameplay ou câmeras em 4K30 / 1080p60 HDR10 com latência ultrabaixa e passthrough HDMI 2.0 4K60 / 1440p120 compatível com VRR em PC, Mac, PS5 e Xbox.',
                'price' => 1399.00,
                'compare_at_price' => 1649.00,
                'cashback_percent' => 7.00,
                'stock' => 16,
            ],
            [
                'category_id' => $streamingAcessorios->id,
                'slug' => 'painel-led-elgato-key-light-air',
                'name' => 'Painel de Luz LED Wi-Fi Elgato Key Light Air 1400 Lúmens com Base',
                'description' => 'Iluminação difusa de 1400 lúmens sem brilho direto nos olhos, temperatura ajustável entre 2900K e 7000K via app Wi-Fi, CRI > 92% e pedestal telescópico de mesa.',
                'price' => 999.00,
                'compare_at_price' => 1199.00,
                'cashback_percent' => 9.00,
                'stock' => 20,
            ],

            // --- MOBILIDADE & ENERGIA ---
            [
                'category_id' => $powerBanksCarregadores->id,
                'slug' => 'power-bank-anker-prime-27650mah-250w',
                'name' => 'Power Bank Anker Prime 27.650mAh 250W PD 3.1 com Tela OLED',
                'description' => 'Capacidade aprovada para cabine de avião (99.54Wh), duas portas USB-C de até 140W cada, recarga total em 37 minutos via dupla entrada USB-C e visor digital de potência em tempo real.',
                'price' => 1099.00,
                'compare_at_price' => 1299.00,
                'cashback_percent' => 8.00,
                'stock' => 21,
            ],
            [
                'category_id' => $powerBanksCarregadores->id,
                'slug' => 'carregador-gan-ugreen-nexode-pro-100w',
                'name' => 'Carregador GaN Rápido UGREEN Nexode Pro 100W 3 Portas USB-C/USB-A',
                'description' => 'Chip GaNInfinity compacto de alta eficiência energética capaz de alimentar um MacBook Pro em alta velocidade simultaneamente com smartphone e fones sem superaquecimento.',
                'price' => 399.00,
                'compare_at_price' => 499.00,
                'cashback_percent' => 10.00,
                'stock' => 50,
            ],
            [
                'category_id' => $powerBanksCarregadores->id,
                'slug' => 'estacao-energia-ecoflow-river-2-pro',
                'name' => 'Estação de Energia Portátil EcoFlow RIVER 2 Pro 768Wh LiFePO4 800W',
                'description' => 'Bateria LiFePO4 de longa vida útil (+3000 ciclos), carga completa em apenas 70 minutos na tomada AC, inversor X-Boost de até 1600W e monitoramento inteligente via aplicativo.',
                'price' => 4690.00,
                'compare_at_price' => 5390.00,
                'cashback_percent' => 6.00,
                'stock' => 8,
            ],
            [
                'category_id' => $mochilasCases->id,
                'slug' => 'mochila-peak-design-everyday-20l-v2',
                'name' => 'Mochila Técnica Peak Design Everyday Backpack V2 20L Cinza Ash',
                'description' => 'Acesso superior magnético MagLatch, aberturas laterais totais com zíperes selados à prova d\'água, divisórias FlexFold modulares para câmera e compartimento dedicado para notebook 16".',
                'price' => 1790.00,
                'compare_at_price' => 2090.00,
                'cashback_percent' => 7.00,
                'stock' => 14,
            ],
            [
                'category_id' => $mochilasCases->id,
                'slug' => 'mochila-executiva-samsonite-ikonn-15',
                'name' => 'Mochila Executiva Samsonite Ikonn para Notebook até 15.6" Preto',
                'description' => 'Design corporativo minimalista em poliéster balístico de alta resistência, bolso frontal organizador com acesso rápido, alça carona para malas de bordo e costado acolchoado.',
                'price' => 549.00,
                'compare_at_price' => 699.00,
                'cashback_percent' => 12.00,
                'stock' => 38,
            ],

            // --- EXPANSÃO CASA, ELETRÔNICOS, INFORMÁTICA, GAMES & CÂMERAS ---
            [
                'category_id' => $eletrodomesticos->id,
                'slug' => 'robo-aspirador-roborock-q-revo',
                'name' => 'Robô Aspirador e Passa Pano Roborock Q Revo com Base Autolimpante',
                'description' => 'Sucção HyperForce de 5500Pa, dois mops giratórios com elevação automática em tapetes, mapeamento LiDAR PreciSense 3D e estação multifunções que lava e seca os panos com ar quente.',
                'price' => 4990.00,
                'compare_at_price' => 5890.00,
                'cashback_percent' => 6.00,
                'stock' => 9,
            ],
            [
                'category_id' => $eletrodomesticos->id,
                'slug' => 'cafeteira-delonghi-magnifica-start',
                'name' => 'Cafeteira Espresso Superautomática De\'Longhi Magnifica Start Inox',
                'description' => 'Moedor cônico em aço com 13 níveis de moagem na hora, preparo de espresso, café longo e vaporizador tradicional de aço inox para espumar leite com acabamento cremoso.',
                'price' => 3690.00,
                'compare_at_price' => 4290.00,
                'cashback_percent' => 7.00,
                'stock' => 12,
            ],
            [
                'category_id' => $eletrodomesticos->id,
                'slug' => 'purificador-ar-xiaomi-smart-4-pro',
                'name' => 'Purificador de Ar Inteligente Xiaomi Smart Air Purifier 4 Pro HEPA',
                'description' => 'Filtragem 3-em-1 que retém 99,97% das partículas de 0,3 mícron (CADR 500m³/h para até 60m²), sensor laser PM2.5/PM10 de alta precisão e visor OLED touch frontal.',
                'price' => 1690.00,
                'compare_at_price' => 1990.00,
                'cashback_percent' => 8.00,
                'stock' => 16,
            ],
            [
                'category_id' => $seguranca->id,
                'slug' => 'roteador-mesh-tplink-deco-xe75-kit2',
                'name' => 'Sistema Wi-Fi 6E Mesh TP-Link Deco XE75 Tri-Band AXE5400 (Kit com 2)',
                'description' => 'Cobertura contínua para até 510m² e 200 dispositivos conectados com banda exclusiva de 6GHz livre de interferências, IA Mesh adaptativa e segurança HomeShield integrada.',
                'price' => 1899.00,
                'compare_at_price' => 2299.00,
                'cashback_percent' => 7.00,
                'stock' => 15,
            ],
            [
                'category_id' => $smartphones->id,
                'slug' => 'smartphone-motorola-edge-50-ultra-512gb',
                'name' => 'Smartphone Motorola Edge 50 Ultra 5G 512GB 12GB RAM Snapdragon 8s Gen 3',
                'description' => 'Tela pOLED Super HD de 144Hz validada pela Pantone, conjunto triplo com teleobjetiva periscópica de 64MP 3x óptico, corpo com acabamento premium e TurboPower 125W.',
                'price' => 4299.00,
                'compare_at_price' => 4999.00,
                'cashback_percent' => 8.00,
                'stock' => 14,
            ],
            [
                'category_id' => $audio->id,
                'slug' => 'soundbar-jbl-bar-800-dolby-atmos',
                'name' => 'Soundbar JBL Bar 800 Pro 720W RMS Dolby Atmos 5.1.2 com Subwoofer 10"',
                'description' => 'Áudio espacial tridimensional real com caixas traseiras destacáveis a bateria recarregável, subwoofer sem fio de 10 polegadas, HDMI eARC 4K Dolby Vision e tecnologia PureVoice.',
                'price' => 4490.00,
                'compare_at_price' => 5190.00,
                'cashback_percent' => 6.00,
                'stock' => 10,
            ],
            [
                'category_id' => $tablets->id,
                'slug' => 'kindle-scribe-32gb-caneta-premium',
                'name' => 'Amazon Kindle Scribe 32GB Tela Paperwhite 10.2" + Caneta Premium',
                'description' => 'Leitor e bloco de notas digital com tela de 300 ppi sem reflexo e textura de papel real para leitura imersiva, anotações em PDFs e cadernos organizados na nuvem.',
                'price' => 2790.00,
                'compare_at_price' => 3190.00,
                'cashback_percent' => 7.00,
                'stock' => 13,
            ],
            [
                'category_id' => $notebooks->id,
                'slug' => 'apple-mac-mini-m4-16gb-512gb',
                'name' => 'Apple Mac mini Chip M4 (CPU 10-Core, 16GB RAM, SSD 512GB) Prateado',
                'description' => 'Computador desktop ultracompacto de 12,7cm com chip Apple M4 para produtividade e IA, 3 portas Thunderbolt 4 traseiras, 2 USB-C frontais e HDMI 2.1 nativo para múltiplos monitores.',
                'price' => 5299.00,
                'compare_at_price' => 5999.00,
                'cashback_percent' => 5.00,
                'stock' => 11,
            ],
            [
                'category_id' => $monitores->id,
                'slug' => 'monitor-lg-dualup-28-ergo-qhd',
                'name' => 'Monitor Ergonômico LG DualUp 28" SDQHD Nano IPS 16:18 com Braço Ergo',
                'description' => 'Proporção vertical 16:18 equivalente a dois monitores QHD empilhados sem emenda, cobertura DCI-P3 98%, porta USB-C com carga de 90W e suporte articulado de fixação na borda da mesa.',
                'price' => 3890.00,
                'compare_at_price' => 4490.00,
                'cashback_percent' => 6.00,
                'stock' => 9,
            ],
            [
                'category_id' => $armazenamento->id,
                'slug' => 'nas-synology-diskstation-ds224-plus',
                'name' => 'Servidor NAS Synology DiskStation DS224+ 2 Baias Intel Celeron J4125',
                'description' => 'Nuvem privada corporativa e doméstica de 2 baias hot-swap com sistema DSM, sincronização segura de arquivos, backup automatizado de fotos e suporte a Btrfs com snapshots.',
                'price' => 2890.00,
                'compare_at_price' => 3390.00,
                'cashback_percent' => 5.00,
                'stock' => 10,
            ],
            [
                'category_id' => $consoles->id,
                'slug' => 'console-portatil-asus-rog-ally-x-1tb',
                'name' => 'Console Portátil ASUS ROG Ally X AMD Ryzen Z1 Extreme 24GB 1TB SSD',
                'description' => 'Tela Full HD IPS de 120Hz com FreeSync Premium, 24GB RAM LPDDR5X rápida, superbateria de 80Wh, dupla entrada USB-C (uma USB4) e ergonômia redesenhada para sessões prolongadas.',
                'price' => 5699.00,
                'compare_at_price' => 6499.00,
                'cashback_percent' => 5.00,
                'stock' => 12,
            ],
            [
                'category_id' => $consoles->id,
                'slug' => 'oculos-vr-meta-quest-3-512gb',
                'name' => 'Headset de Realidade Mista Meta Quest 3 512GB Standalone 4K+',
                'description' => 'Lentes pancake ultrafinas, resolução Infinite Display de 2064x2208 por olho, passthrough colorido em alta definição e dois controles Touch Plus sem anel com retorno háptico TruTouch.',
                'price' => 4399.00,
                'compare_at_price' => 4999.00,
                'cashback_percent' => 6.00,
                'stock' => 14,
            ],
            [
                'category_id' => $camerasAcao->id,
                'slug' => 'camera-dji-osmo-pocket-3-creator-combo',
                'name' => 'Câmera Gimbal de Bolso DJI Osmo Pocket 3 Creator Combo 4K120 1"',
                'description' => 'Sensor CMOS de 1 polegada com D-Log M de 10 bits, tela OLED giratória de 2 polegadas, estabilização mecânica de 3 eixos e kit completo com transmissor sem fio DJI Mic 2 e mini tripé.',
                'price' => 4590.00,
                'compare_at_price' => 5190.00,
                'cashback_percent' => 5.00,
                'stock' => 11,
            ],
        ];

        $saudeBemEstar = Category::updateOrCreate(
            ['slug' => 'saude-bem-estar-tech'],
            ['name' => 'Saúde & Bem-Estar Tech', 'parent_id' => null, 'icon' => 'heart-pulse']
        );
        $monitoramentoFitness = Category::updateOrCreate(
            ['slug' => 'monitoramento-fitness'],
            ['name' => 'Monitoramento & Fitness', 'parent_id' => $saudeBemEstar->id, 'icon' => 'heart-pulse']
        );
        $cuidadosPessoaisTech = Category::updateOrCreate(
            ['slug' => 'cuidados-pessoais-tech'],
            ['name' => 'Cuidados Pessoais Tech', 'parent_id' => $saudeBemEstar->id, 'icon' => 'sparkles']
        );

        $wave2Products = [
            // --- SAÚDE & BEM-ESTAR TECH ---
            [
                'category_id' => $monitoramentoFitness->id,
                'slug' => 'balanca-bioimpedancia-withings-body-smart',
                'name' => 'Balança Inteligente Withings Body Smart com Bioimpedância e Wi-Fi',
                'description' => 'Análise corporal avançada por multifrequência (massa muscular, gordura visceral, água corporal e TMB), frequência cardíaca em pé, modo atleta e sincronização automática Wi-Fi/Bluetooth.',
                'price' => 899.00,
                'compare_at_price' => 1099.00,
                'cashback_percent' => 9.00,
                'stock' => 18,
            ],
            [
                'category_id' => $monitoramentoFitness->id,
                'slug' => 'anel-inteligente-samsung-galaxy-ring',
                'name' => 'Anel Inteligente Samsung Galaxy Ring Titânio Preto com Estojo',
                'description' => 'Estrutura ultraleve em titânio grau 5 com apenas 2,3g, resistência IP68 / 10ATM, sensores contínuos de sono, frequência cardíaca, temperatura da pele e pontuação diária de energia com Galaxy AI.',
                'price' => 2999.00,
                'compare_at_price' => 3499.00,
                'cashback_percent' => 8.00,
                'stock' => 14,
            ],
            [
                'category_id' => $cuidadosPessoaisTech->id,
                'slug' => 'pistola-massageadora-theragun-relief',
                'name' => 'Massageador Percussivo Theragun Relief Terapia Muscular Silenciosa',
                'description' => 'Terapia percussiva ergonômica com empunhadura triangular patenteada, 3 níveis de velocidade confortáveis, pontas macias para recuperação diária e funcionamento ultrassilencioso QuietForce.',
                'price' => 1190.00,
                'compare_at_price' => 1449.00,
                'cashback_percent' => 10.00,
                'stock' => 20,
            ],
            [
                'category_id' => $cuidadosPessoaisTech->id,
                'slug' => 'escova-eletrica-oral-b-io-series-9',
                'name' => 'Escova Dental Elétrica Oral-B iO Series 9 com IA 3D e Estojo Carregador',
                'description' => 'Microvibrações magnéticas silenciosas combinadas com detecção inteligente de 16 superfícies dentárias via Bluetooth, 7 modos de escovação, visor OLED colorido e sensor inteligente de pressão.',
                'price' => 1890.00,
                'compare_at_price' => 2290.00,
                'cashback_percent' => 11.00,
                'stock' => 15,
            ],
            [
                'category_id' => $cuidadosPessoaisTech->id,
                'slug' => 'secador-cabelo-dyson-supersonic-hd15',
                'name' => 'Secador de Cabelos Dyson Supersonic HD15 Motor Digital V9 Difusor Flyaway',
                'description' => 'Motor digital V9 de 110.000 rpm no cabo para equilíbrio ergonômico, controle térmico inteligente 40x por segundo que evita danos por calor extremo e ponteiras magnéticas incluindo acessório antifrizz.',
                'price' => 3390.00,
                'compare_at_price' => 3890.00,
                'cashback_percent' => 7.00,
                'stock' => 9,
            ],

            // --- EXPANSÃO ESCRITÓRIO, INFORMÁTICA, ÁUDIO PRO, GAMES, CASA & CÂMERAS ---
            [
                'category_id' => $perifericos->id,
                'slug' => 'teclado-mecanico-keychron-q1-pro',
                'name' => 'Teclado Mecânico Customizado Keychron Q1 Pro 75% CNC Alumínio Wireless',
                'description' => 'Chassi usinado em alumínio maciço 6063 com gasket mount duplo, compatível com QMK/VIA, Bluetooth 5.1 multi-host para Mac/Windows, switches hot-swappable e knob rotativo programável.',
                'price' => 1490.00,
                'compare_at_price' => 1790.00,
                'cashback_percent' => 8.00,
                'stock' => 16,
            ],
            [
                'category_id' => $streamingAcessorios->id,
                'slug' => 'webcam-insta360-link-2-4k-ia',
                'name' => 'Webcam Profissional Insta360 Link 2 4K Gimbal 2 Eixos Rastreamento IA',
                'description' => 'Sensor CMOS 1/2" com HDR natural, gimbal motorizado silencioso com auto-enquadramento de apresentador, foco ultrarrápido PDAF, modo DeskView superior e microfones com cancelamento de ruído IA.',
                'price' => 1590.00,
                'compare_at_price' => 1890.00,
                'cashback_percent' => 9.00,
                'stock' => 14,
            ],
            [
                'category_id' => $armazenamento->id,
                'slug' => 'ssd-externo-samsung-t9-2tb-usb32',
                'name' => 'SSD Portátil Samsung T9 2TB USB 3.2 Gen 2x2 até 2.000 MB/s Preto',
                'description' => 'Transferências sustentadas de até 2.000 MB/s ideais para edição 4K/ProRes direta no drive, carcaça emborrachada resistente a quedas de 3m, Dynamic Thermal Guard e criptografia AES 256-bit.',
                'price' => 1790.00,
                'compare_at_price' => 2149.00,
                'cashback_percent' => 8.00,
                'stock' => 22,
            ],
            [
                'category_id' => $acessoriosGamer->id,
                'slug' => 'volante-gamer-logitech-g923-trueforce',
                'name' => 'Volante Gamer Logitech G923 TRUEFORCE Force Feedback com Pedais (PS5/PC)',
                'description' => 'Tecnologia de resposta háptica TRUEFORCE ligada em tempo real à física dos jogos, embreagem dupla programável, aro revestido em couro costurado à mão e pedal de freio progressivo.',
                'price' => 2499.00,
                'compare_at_price' => 2899.00,
                'cashback_percent' => 7.00,
                'stock' => 12,
            ],
            [
                'category_id' => $acessoriosGamer->id,
                'slug' => 'headset-gamer-steelseries-arctis-nova-pro',
                'name' => 'Headset Wireless SteelSeries Arctis Nova Pro ANC Base DAC Duas Baterias',
                'description' => 'Cancelamento ativo de ruído híbrido de 4 microfones, sistema infinito Hot-Swap com 2 baterias incluídas, conexão simultânea 2.4GHz + Bluetooth e transmissor OLED multifunção.',
                'price' => 2690.00,
                'compare_at_price' => 3190.00,
                'cashback_percent' => 8.00,
                'stock' => 11,
            ],
            [
                'category_id' => $seguranca->id,
                'slug' => 'fechadura-digital-intelbras-ifr-7000',
                'name' => 'Fechadura Smart Wi-Fi de Embutir Intelbras IFR 7000 Biométrica Tuya',
                'description' => 'Abertura via impressão digital de alta velocidade, senha numérica touch, tag RFID, chave física e aplicativo remoto Wi-Fi compatível com Alexa e Google Assistente com travamento automático.',
                'price' => 1690.00,
                'compare_at_price' => 1990.00,
                'cashback_percent' => 10.00,
                'stock' => 17,
            ],
            [
                'category_id' => $assistentesHubs->id,
                'slug' => 'projetor-portatil-samsung-the-freestyle-2',
                'name' => 'Projetor Smart Portátil Samsung The Freestyle 2ª Geração Full HD 100"',
                'description' => 'Projeção inteligente de 30" a 100" em base giratória 180°, foco e nivelamento automáticos instantâneos, Tizen OS com apps de streaming e Gaming Hub além de som 360° integrado.',
                'price' => 3490.00,
                'compare_at_price' => 4190.00,
                'cashback_percent' => 7.00,
                'stock' => 10,
            ],
            [
                'category_id' => $camerasAcao->id,
                'slug' => 'camera-360-insta360-x4-8k',
                'name' => 'Câmera de Ação 360° Insta360 X4 8K30 Vídeo Imersivo Á Prova D\'Água 10m',
                'description' => 'Gravação em 8K30 e 5.7K60 360° com efeito bastão invisível, estabilização FlowState superior, bateria de 2290mAh até 135 minutos e protetores de lente removíveis de instalação rápida.',
                'price' => 3590.00,
                'compare_at_price' => 4190.00,
                'cashback_percent' => 6.00,
                'stock' => 11,
            ],
            [
                'category_id' => $tablets->id,
                'slug' => 'kindle-colorsoft-signature-32gb',
                'name' => 'Amazon Kindle Colorsoft Signature Edition 32GB Tela Colorida Antirreflexo',
                'description' => 'Tela Colorsoft de 7 polegadas com contraste elevado para capas, gráficos e destaques em cores, ajuste automático da iluminação frontal, recarga sem fio e até 8 semanas de bateria.',
                'price' => 1690.00,
                'compare_at_price' => 1990.00,
                'cashback_percent' => 9.00,
                'stock' => 19,
            ],
            [
                'category_id' => $mesasIluminacao->id,
                'slug' => 'fita-led-philips-hue-gradient-2m',
                'name' => 'Fita LED Inteligente Philips Hue Ambiance Gradient 2m Base Kit RGBWW',
                'description' => 'Degradê suave multicolorido endereçável em toda a extensão da fita, 16 milhões de cores + brancos puros reguláveis para setups de escritório e sincronização via Bluetooth ou Hue Bridge.',
                'price' => 799.00,
                'compare_at_price' => 999.00,
                'cashback_percent' => 11.00,
                'stock' => 26,
            ],
        ];

        $products = array_merge($products, $extraProducts, $wave2Products);

        Cache::forget('nav_categories_tree');

        $productsBySlug = [];
        foreach ($products as $productData) {
            $productData['image_path'] = '/images/products/'.$productData['slug'].'.webp';
            $savedProduct = Product::updateOrCreate(
                ['slug' => $productData['slug']],
                array_merge($productData, ['is_active' => true])
            );
            $productsBySlug[$productData['slug']] = $savedProduct;
        }

        // Kits & Setups Prontos
        $bundlesData = [
            [
                'slug' => 'kit-home-office-executivo',
                'name' => 'Kit Home Office Executivo Completo',
                'subtitle' => 'Ergonomia elétrica, braço duplo e iluminação inteligente para 8h+ produtivas.',
                'description' => 'Combinação curada com Mesa Regulável Elétrica GenioDesks Pro, Cadeira Ergonômica Elements Astra Pro, Luminária BenQ ScreenBar Halo e Fita LED Philips Hue Gradient para um ambiente corporativo impecável.',
                'badge' => 'Setup Executivo',
                'recommended_coupon_code' => 'SETUP15',
                'items' => [
                    'mesa-regulavel-eletrica-geniodesks-pro' => 1,
                    'cadeira-ergonomica-elements-astra-pro' => 1,
                    'luminaria-monitor-benq-screenbar-halo' => 1,
                    'fita-led-philips-hue-gradient-2m' => 1,
                ],
            ],
            [
                'slug' => 'estudio-podcast-streaming-pro',
                'name' => 'Estúdio Podcast & Streaming Pro',
                'subtitle' => 'Captação vocal broadcast, interface Scarlett 4ª geração, vídeo 4K e automação.',
                'description' => 'Tudo para gravações, transmissões ao vivo e reuniões com qualidade de estúdio: Shure SM7dB, Focusrite Scarlett 2i2 4ª Geração, Stream Deck MK.2, Key Light Air e Webcam Insta360 Link 2 4K.',
                'badge' => 'Criadores Pro',
                'recommended_coupon_code' => 'TECH100',
                'items' => [
                    'microfone-dinamico-shure-sm7db' => 1,
                    'interface-audio-focusrite-scarlett-2i2-4g' => 1,
                    'elgato-stream-deck-mk2-15-teclas' => 1,
                    'painel-led-elgato-key-light-air' => 1,
                    'webcam-insta360-link-2-4k-ia' => 1,
                ],
            ],
            [
                'slug' => 'setup-dev-apple-m4',
                'name' => 'Workstation Compacta Apple M4 DualUp',
                'subtitle' => 'Desempenho desktop silencioso com tela vertical 16:18 e teclado customizado.',
                'description' => 'Estação enxuta e silenciosa para engenharia de software e criação: Mac mini M4 16GB/512GB, Monitor Ergonômico LG DualUp 28" com Braço Ergo, Teclado Keychron Q1 Pro e Dock Anker 575 13-em-1.',
                'badge' => 'Engenharia & Tech',
                'recommended_coupon_code' => 'DIGITAL10',
                'items' => [
                    'apple-mac-mini-m4-16gb-512gb' => 1,
                    'monitor-lg-dualup-28-ergo-qhd' => 1,
                    'teclado-mecanico-keychron-q1-pro' => 1,
                    'dock-station-anker-575-usbc-13em1' => 1,
                ],
            ],
            [
                'slug' => 'kit-viagem-trabalho-remoto',
                'name' => 'Kit Mobilidade & Trabalho Remoto',
                'subtitle' => 'Energia 250W liberada para avião, carregador GaN 100W, SSD ultrarrápido e proteção.',
                'description' => 'Autonomia completa fora do escritório com Mochila Peak Design 20L, Power Bank Anker Prime 250W, Carregador UGREEN Nexode Pro 100W e SSD Portátil Samsung T9 2TB.',
                'badge' => 'Nômade Digital',
                'recommended_coupon_code' => 'TECH100',
                'items' => [
                    'mochila-peak-design-everyday-20l-v2' => 1,
                    'power-bank-anker-prime-27650mah-250w' => 1,
                    'carregador-gan-ugreen-nexode-pro-100w' => 1,
                    'ssd-externo-samsung-t9-2tb-usb32' => 1,
                ],
            ],
        ];

        foreach ($bundlesData as $bundleData) {
            $itemsMap = $bundleData['items'];
            unset($bundleData['items']);

            $bundle = ProductBundle::updateOrCreate(
                ['slug' => $bundleData['slug']],
                array_merge($bundleData, ['is_active' => true])
            );

            foreach ($itemsMap as $prodSlug => $qty) {
                if (isset($productsBySlug[$prodSlug])) {
                    ProductBundleItem::updateOrCreate(
                        [
                            'bundle_id' => $bundle->id,
                            'product_id' => $productsBySlug[$prodSlug]->id,
                        ],
                        ['quantity' => $qty]
                    );
                }
            }
        }
    }
}
