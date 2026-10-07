USE prefect_db;

ALTER TABLE clearance_hold
    ADD COLUMN violation_id INT(11) NULL AFTER incident_id,
    ADD COLUMN expected_resolution_date DATE NULL AFTER hold_date,
    ADD COLUMN placed_by INT(11) NULL AFTER reason,
    ADD COLUMN resolution_date DATE NULL AFTER status,
    ADD COLUMN resolution_type VARCHAR(50) NULL AFTER resolution_date,
    ADD COLUMN resolution_notes TEXT NULL AFTER resolution_type,
    ADD COLUMN resolved_by INT(11) NULL AFTER resolution_notes,
    ADD COLUMN release_date DATE NULL AFTER resolved_by,
    ADD COLUMN release_reason TEXT NULL AFTER release_date,
    ADD COLUMN release_notes TEXT NULL AFTER release_reason,
    ADD COLUMN released_by INT(11) NULL AFTER release_notes,
    ADD COLUMN notes TEXT NULL AFTER released_by,
    ADD COLUMN created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    ADD COLUMN updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    MODIFY COLUMN status VARCHAR(30) NOT NULL DEFAULT 'ON_HOLD',
    ADD KEY idx_clearance_hold_student_status (student_id, status),
    ADD KEY idx_clearance_hold_violation (violation_id),
    ADD KEY idx_clearance_hold_placed_by (placed_by),
    ADD KEY idx_clearance_hold_resolved_by (resolved_by),
    ADD KEY idx_clearance_hold_released_by (released_by),
    ADD CONSTRAINT fk_clearance_hold_violation
        FOREIGN KEY (violation_id) REFERENCES infraction_logging (infraction_id)
        ON DELETE SET NULL ON UPDATE CASCADE,
    ADD CONSTRAINT fk_clearance_hold_placed_by
        FOREIGN KEY (placed_by) REFERENCES users (user_id)
        ON DELETE SET NULL ON UPDATE CASCADE,
    ADD CONSTRAINT fk_clearance_hold_resolved_by
        FOREIGN KEY (resolved_by) REFERENCES users (user_id)
        ON DELETE SET NULL ON UPDATE CASCADE,
    ADD CONSTRAINT fk_clearance_hold_released_by
        FOREIGN KEY (released_by) REFERENCES users (user_id)
        ON DELETE SET NULL ON UPDATE CASCADE;

UPDATE clearance_hold
SET status = 'ON_HOLD'
WHERE LOWER(status) = 'active';