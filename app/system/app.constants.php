<?php

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
}
