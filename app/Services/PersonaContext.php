<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Session;

final class PersonaContext
{
    private const SESSION_KEY = 'persona_id_activa';

    public function __construct(
        private readonly PersonaService $people,
        private readonly Session $session,
    ) {
    }

    public function people(): array
    {
        $familyId = (int) $this->session->get('family_id', 0);
        return $familyId > 0 ? $this->people->allForFamily($familyId) : [];
    }

    public function active(): ?array
    {
        $familyId = (int) $this->session->get('family_id', 0);
        $activeId = (int) $this->session->get(self::SESSION_KEY, 0);
        if ($familyId === 0) {
            return null;
        }
        if ($activeId > 0) {
            $person = $this->people->findForFamily($activeId, $familyId);
            if ($person !== null) {
                return $person;
            }
            $this->clear();
        }
        $people = $this->people->allForFamily($familyId);
        if (count($people) === 1) {
            $this->session->put(self::SESSION_KEY, (int) $people[0]['id']);
            return $this->people->findForFamily((int) $people[0]['id'], $familyId);
        }
        return null;
    }

    public function requireActive(): array
    {
        $person = $this->active();
        if ($person === null) {
            throw new \RuntimeException('Selecciona una persona para continuar.');
        }
        return $person;
    }

    public function select(int $personId): array
    {
        $familyId = (int) $this->session->get('family_id', 0);
        $person = $this->people->findForFamily($personId, $familyId);
        if ($person === null) {
            throw new \InvalidArgumentException('La persona seleccionada no pertenece a la familia activa.');
        }
        $this->session->put(self::SESSION_KEY, $personId);
        return $person;
    }

    public function clear(): void
    {
        $this->session->forget(self::SESSION_KEY);
    }

    public function matches(int $personId): bool
    {
        return (int) ($this->active()['id'] ?? 0) === $personId;
    }
}
