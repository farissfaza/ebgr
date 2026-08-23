-- Migration: Add latency_ms columns and duration tracking
-- Run this on your existing database to add latency tracking

-- 1. Add latency_ms to raspi_tasks (per-task round-trip latency)
ALTER TABLE raspi_tasks
ADD COLUMN latency_ms INT NULL COMMENT 'Round-trip latency in milliseconds (updated_at - created_at)' AFTER updated_at;

-- 2. Add latency_ms to checkin_tamu_log (average session latency)
ALTER TABLE checkin_tamu_log
ADD COLUMN latency_ms INT NULL COMMENT 'Average round-trip latency (ms) across all Raspi tasks in this session' AFTER total_amount;

-- 3. Add total_duration_sec to checkin_tamu_log (total time from first hardware button press to submission)
ALTER TABLE checkin_tamu_log
ADD COLUMN total_duration_sec INT NULL COMMENT 'Total time (seconds) from first hardware scan until form submission' AFTER latency_ms;
