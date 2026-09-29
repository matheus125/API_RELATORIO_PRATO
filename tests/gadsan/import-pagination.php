<?php
declare(strict_types=1);
namespace Hcode {
    // Minimal layout double: no DB, authentication or personal data in this regression.
    class PageAdmin {
        public function __destruct() { echo '<footer>fim do layout</footer>'; }
    }
}
namespace Hcode\Model {
    class Funcionarios {
        public static function hasPermission(string $permission): bool { return true; }
    }
}
namespace {
    if (PHP_SAPI!=='cli') exit;
    define('APP_DIR',dirname(__DIR__,2).'/app');
    $app=new class {
        public function get(...$args): void {}
        public function post(...$args): void {}
    };
    require APP_DIR.'/routes/admin-gadsan.php';
    $_SESSION=['gadsan_csrf'=>'token-sintetico'];
    $checks=0;
    set_error_handler(function($severity,$message,$file,$line){throw new \ErrorException($message,0,$severity,$file,$line);});
    try {
        foreach ([1,2,3] as $pageNumber) {
            ob_start();
            gadsanRender('import',[
                'import'=>['id'=>1,'nome_arquivo'=>'fixture.xlsx','status'=>'concluida','total_linhas'=>55,'total_importados'=>55,'total_ignorados'=>0,'total_inconsistencias'=>0],
                'origins'=>[],'pending'=>[],'page'=>$pageNumber,'total'=>55,
            ]);
            $html=ob_get_clean();
            foreach ([
                str_contains($html,'Página '.$pageNumber),
                str_contains($html,'>Anterior</a>')===($pageNumber>1),
                str_contains($html,'>Próxima</a>')===($pageNumber<3),
                str_ends_with($html,'<footer>fim do layout</footer>'),
            ] as $ok) { if (!$ok) throw new \RuntimeException('Regressão na paginação.');$checks++; }
        }
        echo "OK: $checks verificações de renderização e paginação de importações.\n";
    } catch (\Throwable $e) {
        while(ob_get_level())ob_end_clean();
        fwrite(STDERR,'Falha de renderização: '.get_class($e).PHP_EOL);exit(1);
    } finally { restore_error_handler(); }
}
