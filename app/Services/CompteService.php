<?php

namespace App\Services;

use App\Models\Transaction;
use App\Models\Compte;
use App\Models\User;
use App\Repository\CompteRepository;
use Illuminate\Support\Facades\DB;

class CompteService
{
    public CompteRepository $repo;

    public function __construct(CompteRepository $repo)
    {
        $this->repo = $repo;
    }

    public function getOrCreateForUser(User $user): Compte
    {
        $c = $this->repo->getByUser($user);
        if (! $c) {
            $c = $this->repo->createForUser($user);
        }
        return $c;
    }

    public function depot(User $user, float $montant): array
    {
        return DB::transaction(function () use ($user, $montant) {
            $c = $this->getOrCreateForUser($user);

            $txData = [
                'compte_id' => $c->id,
                'type' => 'depot',
                'montant' => $montant,
                'status' => 'completed',
                'created_by' => $user->id,
            ];
            $tx = Transaction::create($txData);

            $new = $c->solde + $montant;
            $this->repo->updateBalance($c, $new);

            return ['compte' => $c, 'transaction' => $tx];
        });
    }

    public function retrait(User $user, float $montant): array
    {
        return DB::transaction(function () use ($user, $montant) {
            $c = $this->getOrCreateForUser($user);
            if ($c->solde < $montant) {
                throw new \Exception('Solde insuffisant');
            }

            $txData = [
                'compte_id' => $c->id,
                'type' => 'retrait',
                'montant' => $montant,
                'status' => 'completed',
                'created_by' => $user->id,
            ];
            $tx = Transaction::create($txData);

            $new = $c->solde - $montant;
            $this->repo->updateBalance($c, $new);

            return ['compte' => $c, 'transaction' => $tx];
        });
    }

    /**
     * Transfer funds from one user to another (by phone or compte id)
     * @param User $from
     * @param array $to Either ['phone' => '...'] or ['compte_id' => '...']
     * @param float $montant
     * @return array
     * @throws \Exception
     */
    public function transfert(User $from, array $to, float $montant): array
    {
        return DB::transaction(function () use ($from, $to, $montant) {
            $fromCompte = $this->getOrCreateForUser($from);

            // resolve recipient compte
            $toCompte = null;
            if (! empty($to['phone'])) {
                $recipient = User::firstWhere('phone', $to['phone']);
                if (! $recipient) {
                    throw new \Exception('Destinataire introuvable');
                }
                $toCompte = $this->getOrCreateForUser($recipient);
            } elseif (! empty($to['compte_id'])) {
                $toCompte = $this->repo->find($to['compte_id']);
                if (! $toCompte) {
                    throw new \Exception('Compte destinataire introuvable');
                }
            } else {
                throw new \Exception('Paramètre destinataire manquant');
            }

            if ($fromCompte->id === $toCompte->id) {
                throw new \Exception('Impossible de transférer vers le même compte');
            }

            if ($fromCompte->solde < $montant) {
                throw new \Exception('Solde insuffisant');
            }

            // debit from
            $txDebitData = [
                'compte_id' => $fromCompte->id,
                'type' => 'transfert_debit',
                'status' => 'completed',
                'counterparty' => $toCompte->id,
                'created_by' => $from->id,
            ];
            $txDebitData['montant'] = $montant;
            $txDebit = Transaction::create($txDebitData);

            // credit to
            $txCreditData = [
                'compte_id' => $toCompte->id,
                'type' => 'transfert_credit',
                'status' => 'completed',
                'counterparty' => $fromCompte->id,
                'created_by' => $from->id,
            ];
            $txCreditData['montant'] = $montant;
            $txCredit = Transaction::create($txCreditData);

            $this->repo->updateBalance($fromCompte, $fromCompte->solde - $montant);
            $this->repo->updateBalance($toCompte, $toCompte->solde + $montant);

            return ['from' => $fromCompte->fresh(), 'to' => $toCompte->fresh(), 'debit_transaction' => $txDebit, 'credit_transaction' => $txCredit];
        });
    }

    /**
     * Pay merchant using a QR code.
     */
    public function payerParQr(User $payer, \App\Models\QrCode $qr, ?float $montant = null): array
    {
        $merchant = \App\Models\User::find($qr->user_id);
        if (! $merchant) {
            throw new \Exception('Commerçant introuvable');
        }

        $montant = $montant ?? ($qr->meta['montant'] ?? null);
        if (! $montant) {
            throw new \Exception('Montant non spécifié');
        }

        return $this->transfert($payer, ['compte_id' => $this->getOrCreateForUser($merchant)->id], (float) $montant);
    }
}
