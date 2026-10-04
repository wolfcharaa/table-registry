SELECT COUNT(*)::bigint AS "total"
FROM public.people_count_source p
LEFT JOIN public.department d ON d.id = p.department_id
WHERE (CAST(concat_ws(' ', p.last_name, p.first_name, p.middle_name) AS text) ILIKE :p0)
  AND CAST(concat_ws(' ', p.last_name, p.first_name, p.middle_name) AS text) ILIKE :p1
  AND p.status IN (:p2, :p3)
  AND p.created_at >= :p4
  AND p.created_at < :p5
  AND p.id IN (:p6)
  AND (p.area_id = :p7)
