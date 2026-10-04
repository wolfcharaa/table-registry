SELECT
    p.id AS "id",
    concat_ws(' ', p.last_name, p.first_name, p.middle_name) AS "fullName",
    d.code AS "departmentCode",
    p.status AS "status",
    p.organization_id AS "organizationId",
    p.area_id AS "areaId",
    p.created_at AS "createdAt",
    p.action_token AS "actionToken"
FROM public.people p
LEFT JOIN public.department d ON d.id = p.department_id
WHERE (CAST(concat_ws(' ', p.last_name, p.first_name, p.middle_name) AS text) ILIKE :p0)
  AND CAST(concat_ws(' ', p.last_name, p.first_name, p.middle_name) AS text) ILIKE :p1
  AND p.status IN (:p2, :p3)
  AND p.created_at >= :p4
  AND p.created_at < :p5
  AND p.id IN (:p6)
  AND (p.area_id = :p7)
ORDER BY p.last_name, p.first_name DESC

LIMIT 10 OFFSET 10
