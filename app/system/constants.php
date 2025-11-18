<?php

/**
 * Core system constants
 */

namespace CORE {
    const SYSADMIN_SECRET_KEY_FILE = VAR_PATH . "/secret-key.txt";
}

/**
 * Sample status constants
 */

namespace SAMPLE_STATUS {
    const ON_HOLD = 1; // Sample is on hold
    const LOST_OR_MISSING = 2; // Sample is lost or missing
    const REORDERED_FOR_TESTING = 3; // Sample has been reordered for testing
    const REJECTED = 4; // Sample has been rejected
    const TEST_FAILED = 5; // Sample test has failed
    const RECEIVED_AT_TESTING_LAB = 6; // Sample has been received at the testing lab
    const ACCEPTED = 7; // Sample has been accepted
    const PENDING_APPROVAL = 8; // Sample is pending approval
    const RECEIVED_AT_CLINIC = 9; // Sample has been received at the clinic
    const EXPIRED = 10; // Sample has expired
    const NO_RESULT = 11; // Sample has no result
    const CANCELLED = 12; // Sample Cancelled - No Testing required
    const REFERRED = 13; // Sample Referred to another Lab
}

/**
 * Country identifiers
 */

namespace COUNTRY {
    const SOUTH_SUDAN = 1;
    const SIERRA_LEONE = 2;
    const DRC = 3;
    const CAMEROON = 4;
    const PNG = 5;
    const WHO = 6;
    const RWANDA = 7;
    const BURKINA_FASO = 8;
}


namespace CLI {
    const OK = 0; // Normal/clean exit (includes deliberate early exits)
    const ERROR = 1; // Application error
    const INVALID_INPUT = 2; // Invalid input provided
    const SIGTERM = 143; // SIGTERM stands for "Signal Termination." It is a generic signal used to request the termination of a process.
    const SIGINT = 130; // SIGINT stands for "Signal for Keyboard Interruption." It is typically generated when a user presses Ctrl+C in the terminal.
}
