<?php

namespace App\Services\Transporte;

use RuntimeException;

/**
 * Erro que vai direto para a tela. A mensagem é escrita para quem opera,
 * dizendo o que falta e onde resolver, nunca o detalhe técnico.
 */
class TransporteException extends RuntimeException {}
