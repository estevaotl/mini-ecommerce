<?php
namespace Api\Services;

class ValidacaoGestao extends \RuntimeException {
    public function __construct(public array $campos) {
        parent::__construct('Confira os campos indicados para salvar.');
    }
}
