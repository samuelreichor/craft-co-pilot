<?php

namespace samuelreichor\coPilot\enums;

enum Provider: string
{
    case OpenAI = 'openai';
    case Anthropic = 'anthropic';
    case Gemini = 'gemini';
}
