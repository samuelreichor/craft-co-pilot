<?php

namespace samuelreichor\coPilot\enums;

enum MessageRole: string
{
    case User = 'user';
    case Assistant = 'assistant';
    case Tool = 'tool';
}
