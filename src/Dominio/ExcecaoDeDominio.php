<?php

declare(strict_types=1);

namespace ControleConcreto\Dominio;

use DomainException;

/**
 * Regra de negócio ou de norma violada. A mensagem é escrita para quem está
 * usando o sistema, não para quem está depurando.
 */
final class ExcecaoDeDominio extends DomainException
{
}
