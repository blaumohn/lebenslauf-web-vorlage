<?php

namespace App\Http\Security;

/**
 * Prüft, ob zu einem Token-Profil überhaupt privater Inhalt existiert.
 * Eigene Implementierung je Seitentyp der Vorlage.
 */
interface TokenSubjectResolver
{
    /** @param non-empty-string $profile */
    public function exists(string $profile): bool;
}
