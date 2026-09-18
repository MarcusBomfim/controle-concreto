<?php

declare(strict_types=1);

namespace ControleConcreto\Dominio\Usuario;

/**
 * Quem faz o quê no controle tecnológico.
 *
 * O laboratorista vive do dia a dia: recebe o caminhão, molda, rompe. O
 * engenheiro responde pela estrutura: cadastra o que será concretado, forma
 * e julga os lotes e trata o que reprovou. O gestor acompanha. As permissões
 * ficam aqui, num lugar só, e não em ifs espalhados pelos controladores.
 */
enum Papel: string
{
    case Engenheiro = 'engenheiro';
    case Laboratorista = 'laboratorista';
    case Gestor = 'gestor';

    public function rotulo(): string
    {
        return match ($this) {
            self::Engenheiro => 'Engenheiro',
            self::Laboratorista => 'Laboratorista',
            self::Gestor => 'Gestor',
        };
    }

    /** Receber carga, moldar, romper, descartar: o trabalho de campo e de prensa. */
    public function podeOperar(): bool
    {
        return $this !== self::Gestor;
    }

    /**
     * Cadastrar obra e elemento, formar e julgar lote, tratar não
     * conformidade: são decisões sobre a estrutura, e quem responde por ela
     * é o engenheiro.
     */
    public function podeDecidir(): bool
    {
        return $this === self::Engenheiro;
    }

    /** Todo mundo lê. O gestor existe justamente para acompanhar. */
    public function podeConsultar(): bool
    {
        return true;
    }

    public function descricaoDasPermissoes(): string
    {
        return match ($this) {
            self::Engenheiro => 'Acesso completo: cadastros, concretagens, laboratório, lotes e não conformidades.',
            self::Laboratorista => 'Registra concretagens, cargas, moldagens e resultados de ensaio. Não forma nem julga lote.',
            self::Gestor => 'Somente leitura: acompanha a agenda, as obras e os lotes.',
        };
    }
}
