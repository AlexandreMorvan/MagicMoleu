<?php

namespace App\Collection;

/**
 * Fichier CSV refusé (vide, illisible, colonnes manquantes) ; le message s'adresse à l'utilisateur.
 */
class CsvImportException extends \RuntimeException
{
}
