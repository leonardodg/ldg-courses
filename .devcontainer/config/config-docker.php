<?php

unset($CFG);
global $CFG;
$CFG = new stdClass();

function get_moodle_setting($env_name, $secret_path = null) {
    // 1. Tenta pegar da variável de ambiente tradicional
    $val = getenv($env_name);
    if ($val !== false && $val !== '') {
        return $val;
    }

    // 2. Se não achar e houver um caminho de secret, tenta ler o arquivo
    if ($secret_path && file_exists($secret_path)) {
        $secret_val = trim(file_get_contents($secret_path));
        if ($secret_val !== '') {
            return $secret_val;
        }
    }

    return null;
}

$CFG->dbtype    = getenv('MOODLE_DBTYPE') ?: 'mariadb';
$CFG->dblibrary = getenv('MOODLE_DBLIB') ?: 'native';
$CFG->dbhost    = getenv('MOODLE_DBHOST') ?: 'db';
$CFG->dbname    = getenv('MOODLE_DBNAME') ?: 'moodle';
$CFG->prefix    = getenv('MOODLE_DBPFX') ?: 'mdl_';
$CFG->dboptions = array('dbcollation' => getenv('MOODLE_DBCOLLATION') ?: 'utf8mb4_bin');

$CFG->dbuser    = get_moodle_setting('MOODLE_DBUSER', '/run/secrets/db_user');
$CFG->dbpass    = get_moodle_setting('MOODLE_DBPASS', '/run/secrets/db_password');

$CFG->wwwroot   = getenv('MOODLE_URL') ?: 'https://develop.local';
$CFG->dataroot  = getenv('MOODLE_DATA') ?: '/var/www/moodledata';
$CFG->admin     = getenv('MOODLE_ADMIN') ?: 'admin';

$CFG->directorypermissions = 0777;
$CFG->sslproxy  = true;

// 1 = GETREMOTEADDR_SKIP_HTTP_CLIENT_IP, que ainda nao existe aqui. Le o
// X-Forwarded-For, onde o core usa o ULTIMO valor: o que o nginx acrescentou, e
// nao o que o cliente mandou. Sem isto, todo acesso aparece com o IP do gateway
// do Docker, e log de login e bloqueio por IP perdem o sentido.
$CFG->getremoteaddrconf = 1;

// -----------------------------------------------------------------------------
// Dominio por vendedor.
//
// O local_marketplace grava um mapa Host -> empresa no dataroot sempre que um
// dominio muda. Aqui ele e LIDO, e nao consultado no banco, porque este arquivo
// roda antes do lib/setup.php: $DB ainda nao existe.
//
// CLI e cron ficam de fora. Eles nao tem HTTP_HOST, e um e-mail disparado pelo
// cron precisa sair com o dominio da plataforma - nao com o do ultimo vendedor
// que por acaso estivesse na fila.
//
// NAO defina $CFG->sessioncookiedomain junto com isto. Sem ele o cookie fica
// escopado por host e a sessao passa a ser por dominio: quem entra em
// meuscursos.joao.com NAO esta logado em courses.leodg.dev. E o comportamento
// desejado - dominios de vendedores diferentes nao compartilham sessao - mas
// precisa estar claro na UX do checkout.
$CFG->wwwrootdefault = $CFG->wwwroot;

if (PHP_SAPI !== 'cli' && !empty($_SERVER['HTTP_HOST'])) {
    $marketplacemap = @include($CFG->dataroot . '/marketplace_domains.php');
    // strtolower porque o navegador pode mandar o Host em qualquer caixa, e a
    // chave do mapa e gravada em minusculas.
    $marketplacehost = strtolower($_SERVER['HTTP_HOST']);

    if (is_array($marketplacemap) && isset($marketplacemap[$marketplacehost]['wwwroot'])) {
        $CFG->wwwroot = $marketplacemap[$marketplacehost]['wwwroot'];
        // Guardado para o plugin saber de qual empresa e a requisicao sem ter
        // que reabrir o arquivo depois.
        $CFG->marketplacecompany = $marketplacemap[$marketplacehost]['company'];
    }
    unset($marketplacemap, $marketplacehost);
}
// -----------------------------------------------------------------------------

// Debug: respeita MOODLE_DEBUG/MOODLE_DEBUG_DISPLAY do ambiente.
// DEVELOPER = 32767 (E_ALL); qualquer outro valor cai para NONE.
$debugenv = strtoupper((string) getenv('MOODLE_DEBUG'));
$CFG->debug = ($debugenv === 'DEVELOPER') ? 32767 : 0;
$CFG->debugdisplay = (int) (getenv('MOODLE_DEBUG_DISPLAY') ?: 0);

// Cache de strings segue o debug: editar lang e ver o efeito sem purgar cache
// so interessa a quem esta editando, e o custo de deixar desligado e pequeno.
$CFG->langstringcache = ($debugenv !== 'DEVELOPER');

// cachejs NAO segue o debug. E controlado a parte, e vem LIGADO por padrao.
//
// Com ele desligado o Moodle serve CADA modulo AMD numa requisicao separada. O
// modal de pagamento depende de varios modulos do core, entao a janela entre o
// botao "Comprar" aparecer e o handler de clique existir passa de
// milissegundos para segundos - e o aluno precisa clicar DUAS vezes para
// conseguir pagar. Foi o que aconteceu em courses.leodg.dev, que rodava com
// MOODLE_DEBUG=DEVELOPER.
//
// Amarrar isto ao debug cobrava de quem compra o conforto de quem edita JS.
// Quem precisa mesmo do JS sem bundle liga MOODLE_CACHEJS=false por tempo
// determinado, sem levar junto as mensagens de debug.
$cachejsenv = getenv('MOODLE_CACHEJS');
$CFG->cachejs = ($cachejsenv === false || $cachejsenv === '')
    ? true
    : filter_var($cachejsenv, FILTER_VALIDATE_BOOLEAN);

$CFG->preventexecpath = true;
$CFG->localcachedir = '/var/www/moodledata/localcache';

$CFG->phpunit_dataroot  = '/var/www/phpunitdata';
$CFG->phpunit_prefix = 't_';
define('PHPUNIT_LONGTEST', true);

// -----------------------------------------------------------------------------
// Behat.
//
// Fica AQUI, no template versionado, e nao no config-local.php como a primeira
// rodada de behat instruiu. Nenhum destes tres valores e verdadeiro em uma
// maquina so: sao a mesma coisa em toda worktree e em todo clone. Deixa-los no
// arquivo de maquina significava que cada ambiente novo nascia sem behat e
// alguem tinha que descobrir isso de novo.
//
// O behat DERRUBA E RECRIA o banco e o dataroot que ele usa - por isso prefixo
// e dataroot proprios, separados do site de trabalho e do phpunit.
$CFG->behat_dataroot = '/var/www/behatdata';
$CFG->behat_prefix = 'bht_';

// 0.0.0.0, e nao 127.0.0.1, no servidor que atende este endereco: o Chrome roda
// em OUTRO container, e um servidor presO ao loopback do container do Moodle
// seria invisivel para ele. O nome 'moodle' resolve pela rede do compose.
$CFG->behat_wwwroot = 'http://moodle:8000';

// Perfil para os cenarios @javascript, que exigem navegador de verdade.
//
// O servico 'selenium' do compose/behat.yml nao sobe junto com o stack: Chrome
// ocupa memoria e a maioria das rodadas nao precisa dele. Suba sob demanda -
// ver docs/dev/behat.md.
$CFG->behat_profiles = [
    'chrome' => [
        'browser' => 'chrome',
        'wd_host' => 'http://selenium:4444/wd/hub',
        'capabilities' => [
            'extra_capabilities' => [
                'goog:chromeOptions' => [
                    // --no-sandbox e exigencia de Chrome dentro de container.
                    // --disable-dev-shm-usage evita o travamento classico em
                    // /dev/shm pequeno, que aparece como aba morrendo no meio
                    // do cenario.
                    'args' => ['--no-sandbox', '--disable-dev-shm-usage', '--window-size=1280,900'],
                ],
            ],
        ],
    ],
];

// -----------------------------------------------------------------------------
// Ajuste local da maquina.
//
// ESTE ARQUIVO E GERADO. O deploy sobrescreve config.php a cada execucao, entao
// editar aqui nao sobrevive ao proximo deploy. Ajuste especifico de uma maquina
// vai em config-local.php, ao lado deste arquivo.
//
// A separacao existe porque o desenho anterior preservava o config.php da VPS
// para nao perder ajuste manual - e o efeito colateral era que mudanca no
// template nunca chegava sozinha. Uma correcao de cachejs ficou semanas sem
// efeito por isso, e recria-lo a mao derrubou o site uma vez.
//
// Vem DEPOIS de todo o resto e ANTES do setup.php: pode sobrescrever qualquer
// $CFG definido acima, e ainda esta a tempo de valer.
//
// O config-local.php fica no EXCLUDE do rsync, como o .env - o deploy nunca o
// toca, nem para criar nem para apagar.
if (file_exists(__DIR__ . '/config-local.php')) {
    require_once(__DIR__ . '/config-local.php');
}

require_once(__DIR__ . '/lib/setup.php');