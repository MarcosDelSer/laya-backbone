-- 017: Disable school-oriented modules not applicable to kindergarten
-- Modules: Departments, Markbook, Crowd Assessment, Timetable Admin,
--          Timetable, Formal Assessment, Rubrics, Library, Tracking

UPDATE gibbonModule SET active='N'
WHERE gibbonModuleID IN (
    '0004',  -- Departments
    '0007',  -- Markbook
    '0012',  -- Crowd Assessment
    '0013',  -- Timetable Admin
    '0014',  -- Timetable
    '0016',  -- Formal Assessment
    '0126',  -- Rubrics
    '0130',  -- Library
    '0141'   -- Tracking
);
