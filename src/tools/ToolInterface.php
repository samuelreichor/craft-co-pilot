<?php

namespace samuelreichor\coPilot\tools;

interface ToolInterface
{
    /**
     * Returns the tool name used in AI function calling.
     */
    public function getName(): string;

    /**
     * Returns the tool description for the AI.
     */
    public function getDescription(): string;

    /**
     * Returns the JSON Schema parameters definition.
     *
     * @return array<string, mixed>
     */
    public function getParameters(): array;

    /**
     * Executes the tool with the given arguments.
     *
     * @param array<string, mixed> $arguments
     * @return array<string, mixed>
     */
    public function execute(array $arguments): array;
}
