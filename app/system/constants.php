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
