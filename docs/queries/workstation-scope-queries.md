# Workstation scope queries

Katalog: `app/Source/V1/Queries/Structure/WorkstationScopeQuery.php`  
Serwis: `app/Source/V1/Services/Structure/WorkstationScopeService.php`

Wspólna warstwa scope dla list globalnych (m.in. przypisania rejestrów). Nie dotyczy endpointów kontekstowych (`/documents/...`, `/cases/...`).

## Wejście

`konfiguracja.madkomWorkstationIds` — wymagane; pusta tablica → `[err_10_appendWorkstationScope]`.

## Profile

| Profil | Konsument | Filtr SQL |
|--------|-----------|-----------|
| `RegistryBrowse` | `/registry-assignments` | `r."galaxia_activities_activityId" IN (...)` |
| `RpwEntryList` | `/registry-assignments-rpw` | `rz.workstation IN (...)` lub brak (uprawnienie `pisma_wychodzace`) |

## Activity IDs (`RegistryBrowse`)

```sql
SELECT DISTINCT gar."activityId"
FROM users_usergroups uug
INNER JOIN galaxia_user_roles gur ON gur."userGroupId" = uug.id
INNER JOIN galaxia_activity_roles gar ON gar."roleId" = gur."roleId"
WHERE uug.group_id IN (...)
  AND uug.status = 'A'
```

## RPW scope (`RpwEntryList`)

1. Jeśli stanowisko ma `users_permissions.symbol = 'pisma_wychodzace'` z wartością `y` → `isUnrestricted = true` (brak filtra `rz.workstation`).
2. W przeciwnym razie: `rz.workstation IN (...)` po `expandWorkstationIdsWithIncludedGroup` (gdy `eurzad2_p_pismo_wychodzace_group_include`).

## Walidacja stanowisk

`users_groups.group_id IN (...)` AND `groupStatus = 'A'`. Nieznane ID → błąd walidacji (422).
