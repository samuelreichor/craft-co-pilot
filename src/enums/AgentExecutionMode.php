<?php

namespace samuelreichor\coPilot\enums;

enum AgentExecutionMode: string
{
    case Supervised = 'supervised';
    case Autonomous = 'autonomous';
}
