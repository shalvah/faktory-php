<?php

namespace Knuckles\Faktory\Connection;

enum State
{
    case Connecting;
    case Connected;
    case Disconnected;
}
