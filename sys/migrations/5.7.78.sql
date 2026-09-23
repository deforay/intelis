-- Migration file for version 5.7.78
-- Created on 2026-09-22 22:00:00

-- Instrument machines: known by their instrument and the lab's own id for them.
-- Each lab numbers its machines from 1, and the STS stored them under that id, so
-- a second lab's machine 1 was dropped. Results carry the lab's id for the machine
-- (import_machine_name) next to the instrument, so the pair is what identifies it.
-- On a lab config_machine_id stays unique; its index keeps AUTO_INCREMENT working.
ALTER TABLE `instrument_machines` ADD INDEX `idx_instrument_machines_config_machine_id` (`config_machine_id`);
ALTER TABLE `instrument_machines` DROP PRIMARY KEY, ADD PRIMARY KEY (`instrument_id`, `config_machine_id`);
-- END OF VERSION --
-- END OF VERSION --
-- END OF VERSION --
-- END OF VERSION --
-- END OF VERSION --
-- END OF VERSION --
-- END OF VERSION --
-- END OF VERSION --
-- END OF VERSION --
-- END OF VERSION --
-- END OF VERSION --
-- END OF VERSION --
