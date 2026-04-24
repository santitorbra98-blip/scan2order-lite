<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class LegalController extends Controller
{
    public function meta(): JsonResponse
    {
        $data = Cache::remember('legal_meta', 3600, function () {
            return [
                'brand_name' => (string) config('legal.brand_name'),
                'company_name' => (string) config('legal.company_name'),
                'owner_type' => (string) config('legal.owner_type'),
                'tax_id' => (string) config('legal.tax_id'),
                'address' => (string) config('legal.address'),
                'postal_code' => (string) config('legal.postal_code'),
                'city' => (string) config('legal.city'),
                'province' => (string) config('legal.province'),
                'country' => (string) config('legal.country'),
                'fiscal_region' => (string) config('legal.fiscal_region'),
                'is_canary_islands' => (bool) config('legal.is_canary_islands'),
                'indirect_tax_name' => (string) config('legal.indirect_tax_name'),
                'contact_email' => (string) config('legal.contact_email'),
                'support_email' => (string) config('legal.support_email'),
                'privacy_email' => (string) config('legal.privacy_email'),
                'support_phone' => (string) config('legal.support_phone'),
                'registry_data' => (string) config('legal.registry_data'),
                'activity_description' => (string) config('legal.activity_description'),
                'version' => (string) config('legal.version'),
                'last_updated' => (string) config('legal.last_updated'),
                'jurisdiction_city' => (string) config('legal.jurisdiction_city'),
                'uses_optional_cookies' => (bool) config('legal.uses_optional_cookies'),
            ];
        });

        return response()->json($data);
    }

    public function acceptances(Request $request): JsonResponse
    {
        $user = $request->user();

        $data = $request->validate([
            'accept_terms' => 'required|accepted',
            'accept_privacy' => 'required|accepted',
            'accept_marketing' => 'nullable|boolean',
        ]);

        $user->forceFill([
            'terms_accepted_at' => now(),
            'privacy_accepted_at' => now(),
            'marketing_consent_at' => array_key_exists('accept_marketing', $data)
                ? ($data['accept_marketing'] ? now() : null)
                : $user->marketing_consent_at,
            'legal_version' => (string) config('legal.version'),
            'legal_acceptance_ip' => $request->ip(),
            'legal_acceptance_user_agent' => substr((string) $request->userAgent(), 0, 1000),
        ])->save();

        $user->load('role');

        $this->auditAction(
            actor: $user,
            action: 'legal.acceptances_updated',
            resourceType: 'user',
            resourceId: $user->id,
            targetUser: $user,
            metadata: [
                'legal_version' => (string) config('legal.version'),
                'marketing_opt_in' => $user->marketing_consent_at !== null,
            ],
            ipAddress: $request->ip(),
            userAgent: (string) $request->userAgent(),
        );

        return response()->json([
            'message' => 'Aceptaciones legales actualizadas correctamente.',
            'user' => [
                'id' => $user->id,
                'terms_accepted_at' => $user->terms_accepted_at?->toIso8601String(),
                'privacy_accepted_at' => $user->privacy_accepted_at?->toIso8601String(),
                'marketing_consent_at' => $user->marketing_consent_at?->toIso8601String(),
                'legal_version' => $user->legal_version,
                'requires_legal_acceptance' => $user->terms_accepted_at === null || $user->privacy_accepted_at === null,
            ],
        ]);
    }
}
