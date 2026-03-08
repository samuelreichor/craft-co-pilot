<?php

namespace samuelreichor\coPilot\enums;

enum ElementUpdateBehavior: string
{
    case ProvisionalDraft = 'provisionalDraft';
    case DirectSave = 'directSave';
    case Draft = 'draft';
}
