<?php

declare(strict_types=1);

namespace App\Services\Agents;

use App\Services\AI\AiGateway;

/**
 * The single generic interview agent (docs/51 §2). Rather than nine near-identical
 * subclasses, one concrete {@see BaseAgent} is instantiated per catalog entry — its
 * identity (key / type / lens / weight) comes from the `ai_agents` row merged with
 * config/ai_agents.php. The AgentRunner builds the nine effective agents from that
 * catalog and shares one AiGateway across them, which keeps the layer DRY while the
 * 0041 system rows remain the source of truth for which agents exist.
 */
final class Agent extends BaseAgent
{
    /**
     * Build an agent from a resolved catalog descriptor.
     *
     * @param array{key:string, agent_type?:string, type?:string,
     *              lens?:string, weight?:float|int} $descriptor
     */
    public static function fromDescriptor(AiGateway $gateway, array $descriptor): self
    {
        return new self(
            $gateway,
            (string) ($descriptor['key'] ?? ''),
            (string) ($descriptor['agent_type'] ?? $descriptor['type'] ?? 'scoring'),
            (string) ($descriptor['lens'] ?? 'You are an interview evaluator. Return a concise judgement.'),
            (float) ($descriptor['weight'] ?? 0),
        );
    }
}
