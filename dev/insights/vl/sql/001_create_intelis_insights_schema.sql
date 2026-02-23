-- ============================================================
-- Intelis Insights — Schema Creation
-- ============================================================
-- Separate schema ensures the analytics user (insights_ro)
-- cannot access operational tables containing PII.
-- ============================================================

CREATE DATABASE IF NOT EXISTS `intelis_insights`
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_0900_ai_ci;

-- Users:
--   insights_ro  : SELECT ONLY on intelis_insights.*
--   insights_etl : SELECT on operational tables + write on intelis_insights.*
-- See vl_privacy_and_privileges.md for full SQL.
