<?php

namespace App\Source\V1\Queries\Document;

use App\Source\V1\Enum\DocumentQueryContext;
use App\Source\V1\Enum\TypDokumentu;
use Exception;

/**
 * Class QueryBuilder
 *
 * @package Docflow\ESBService\Helper\Document\Collection
 */
class QueryBuilder
{
    /**
     * @var int
     */
    private $queryNumber = 0;
    /**
     * @var string
     */
    private $documentGroupColumn = 'document_group_number';

    /**
     * @param $queryContext
     * @param $documentType
     * @param $documentUids
     * @param $filter
     *
     * @return string
     * @throws \ReflectionException
     */
    public function buildSQLQuery(
        int $queryContext,
        int $documentType,
        array $documentUids = []
    ) {
        $sqlQuery =
            $this->addSQLSelect($documentType) .
            $this->addSQLFromAndJoins($queryContext, $documentType) .
            $this->addSQLWhereClause($queryContext, $documentType, $documentUids) .
            $this->addSQLOrderClause($documentType);

        return $sqlQuery;
    }

    /**
     * @param $documentType
     *
     * @return string
     * @throws Exception
     * @throws \ReflectionException
     */
    private function addSQLSelect($documentType)
    {
        $this->queryNumber++;

        switch ($documentType) {
            case TypDokumentu::PISMO:
                return "
                    SELECT
                      DISTINCT ON (id_dokumentu)
                      es.sprawa_uid AS id_dokumentu,
                      gp.name AS nazwa_procesu,
                      gp.\"pId\" AS id_procesu,
                      ess.opis AS status_procesu,
                      -- data_i_czas
                      NULL AS wersja,
                      -- przesylka
                      gp.typ AS typ,
                      " . $this->queryNumber . " AS " . $this->documentGroupColumn . "
                ";
            case TypDokumentu::DOKUMENT:
                return "
                    SELECT
                      DISTINCT ON (id_dokumentu)
                      ep.pismo_uid AS id_dokumentu,
                      gp.name AS nazwa_procesu,
                      gp.\"pId\" AS id_procesu,
                      ess.opis AS status_procesu,
                      -- data_i_czas
                      ep.pismo_wersja AS wersja,
                      -- przesylka
                      gp.typ AS typ,
                      " . $this->queryNumber . " AS " . $this->documentGroupColumn . "
                ";
            default:
                throw new Exception(
                    "Nieprawidłowy rodzaj dokumentu '{$documentType}'"
                );
        }
    }

    /**
     * @param $queryContext
     * @param $documentType
     *
     * @return string
     * @throws Exception
     * @throws \ReflectionException
     */
    private function addSQLFromAndJoins($queryContext, $documentType)
    {
        switch ($documentType) {
            case TypDokumentu::PISMO:
                return $this->addSQLFromAndJoinsForMainDocument($queryContext);
            case TypDokumentu::DOKUMENT:
                return $this->addSQLFromAndJoinsForPlainDocument($queryContext);
            default:
                throw new Exception(
                    "Nieprawidłowy rodzaj dokumentu '{$documentType}'"
                );
        }
    }

    /**
     * @param $queryContext
     *
     * @return string
     * @throws Exception
     */
    private function addSQLFromAndJoinsForMainDocument($queryContext)
    {
        $sql = '';

        switch ($queryContext) {
            case DocumentQueryContext::DOCUMENT_UIDS:
                $sql .= <<<SQL
                    FROM eurzad_sprawa es
SQL;
                break;
            case DocumentQueryContext::CASE_UID:
                $sql .= <<<SQL
                    FROM eurzad_teczka et
                    INNER JOIN eurzad_sprawa es ON es.sprawa_uid = et.sprawa_uid
SQL;
                break;
            case DocumentQueryContext::CASE_UID_MAIN_DOCUMENT_ATTACHED_TO_CASE:
                $sql .= <<<SQL
                    FROM eurzad_teczka et
                    INNER JOIN eurzad_teczka_zawartosc etz ON etz.teczka_uid = et.teczka_uid
                     INNER JOIN eurzad_sprawa es ON es.sprawa_uid = etz.teczka_zawartosc_uid
SQL;
                break;
            case DocumentQueryContext::CASE_UID_MAIN_DOCUMENT_ATTACHED_TO_DOCUMENT:
                $sql .= <<<SQL
                    FROM eurzad_teczka et
                    INNER JOIN eurzad_teczka_zawartosc etz ON etz.teczka_uid = et.teczka_uid
                    INNER JOIN eurzad_teczka_zawartosc etz2 ON etz.teczka_zawartosc_uid = etz2.teczka_uid
                    INNER JOIN eurzad_sprawa es ON es.sprawa_uid = etz2.teczka_zawartosc_uid
SQL;
                break;
            default:
                throw new Exception(
                    "Nieprawidłowy kontekst dokumentu '{$queryContext}'",
                    ErrorCode::DOCUMENT_INCORRECT_QUERY_CONTEXT
                );
        }
        $sql .= <<<SQL
--             INNER JOIN esbservice_queue eq ON eq.main_document_uid = es.sprawa_uid
            INNER JOIN galaxia_processes gp ON gp.normalized_name = es.form_name
            INNER JOIN eurzad_obieg eo ON eo.sprawa_uid = es.sprawa_uid
            INNER JOIN eurzad_slownik_status ess ON ess.symbol = eo.status
            INNER JOIN galaxia_instances gi ON gi."instanceId" = eo."instanceId" AND max_status_sprawy_id > 0
            INNER JOIN eurzad_sprawa_przedluzanie sp on sp.sprawa_uid = es.sprawa_uid 
SQL;

        return $sql;
    }

    /**
     * @param $queryContext
     *
     * @return string
     * @throws Exception
     * @throws \ReflectionException
     */
    private function addSQLFromAndJoinsForPlainDocument($queryContext)
    {
        $sql = '';

        switch ($queryContext) {
            case DocumentQueryContext::DOCUMENT_UIDS:
                $sql .= <<<SQL
                    FROM eurzad_pismo ep
SQL;
                break;
            case DocumentQueryContext::CASE_UID:
                $sql .= <<<SQL
                    FROM eurzad_teczka et
                        INNER JOIN eurzad_teczka_zawartosc etz ON etz.teczka_uid = et.teczka_uid
                        INNER JOIN eurzad_pismo ep ON ep.pismo_uid = etz.teczka_zawartosc_uid
SQL;
                break;
            default:
                throw new Exception(
                    "Nieprawidłowy kontekst dokumentu '{$queryContext}'",
                    ErrorCode::DOCUMENT_INCORRECT_QUERY_CONTEXT
                );
        }
        $sql .= <<<SQL
--             INNER JOIN esbservice_queue eq ON eq.document_uid = ep.pismo_uid
            INNER JOIN galaxia_instances gi ON gi."instanceId" = ep.instance_id
            INNER JOIN galaxia_processes gp ON gp."pId" = gi."pId"
            INNER JOIN eurzad_pismo_obieg epo ON epo.pismo_uid = ep.pismo_uid
            INNER JOIN eurzad_slownik_status ess ON ess.symbol = epo.status
SQL;

        return $sql;
    }

    /**
     * @param int $queryContext
     * @param int $documentType
     * @param array $documentUids
     *
     * @return string
     * @throws \ReflectionException
     */
    private function addSQLWhereClause(int $queryContext, int $documentType, array $documentUids): string
    {
        switch ($queryContext) {
            case DocumentQueryContext::DOCUMENT_UIDS:
                return $this->addSQLWhereClauseForDocuments($documentType, $documentUids);
            case DocumentQueryContext::CASE_UID_MAIN_DOCUMENT_ATTACHED_TO_DOCUMENT:
            case DocumentQueryContext::CASE_UID_MAIN_DOCUMENT_ATTACHED_TO_CASE:
            case DocumentQueryContext::CASE_UID:
                return '
                    WHERE et.teczka_uid = ?
                ';
            default:
                throw new Exception(
                    "Nieprawidłowy kontekst dokumentu '{$queryContext}'"
                );
        }
    }

    /**
     * @param     $documentType
     * @param     $documentIds
     *
     * @return string
     * @throws \ReflectionException
     */
    private function addSQLWhereClauseForDocuments($documentType, $documentIds)
    {
        switch ($documentType) {
            case TypDokumentu::PISMO:
                return "
                    WHERE es.sprawa_uid IN ('" . implode("','", array_map('strval', $documentIds)) . "')
                ";
            case TypDokumentu::DOKUMENT:
                return "
                    WHERE ep.pismo_uid IN ('" . implode("','", array_map('strval', $documentIds)) . "')
                    AND ep.pismo_wersja = (SELECT MAX(pismo_wersja) FROM eurzad_pismo WHERE pismo_uid =  ep.pismo_uid)
                ";
            default:
                throw new Exception(
                    "Nieprawidłowy rodzaj dokumentu '{$documentType}'"
                );
        }
    }

    /**
     * @param string $documentType
     *
     * @return string
     * @throws Exception
     * @throws \ReflectionException
     */
    private function addSQLOrderClause($documentType)
    {
        $orderBy = '
            ORDER BY id_dokumentu ASC,
        ';

        switch ($documentType) {
            case TypDokumentu::PISMO:
                $orderBy .= '
                    eo.status_sprawy_id DESC
                ';
                break;
            case TypDokumentu::DOKUMENT:
                $orderBy .= '
                    epo.pismo_obieg_id DESC
                ';
                break;
            default:
                throw new Exception(
                    "Nieprawidłowy rodzaj dokumentu '{$documentType}'"
                );
        }

        return $orderBy;
    }

    /**
     * @return string
     */
    public function addDocumentGroupOrder()
    {
        return ' ORDER BY ' . $this->documentGroupColumn . ' ASC';
    }
}
