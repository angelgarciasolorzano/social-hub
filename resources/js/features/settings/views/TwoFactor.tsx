import { useState } from "react";

import { Form, usePage } from "@inertiajs/react";

import { ShieldBan, ShieldCheck } from "lucide-react";

import { disable, enable } from "@/routes/two-factor";

import HeadingSmall from "@/components/header/heading-small";
import { TwoFactorRecoveryCodes, TwoFactorSetupModal } from "@/components/twoFactor";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";

import { useTwoFactorAuth } from "@/hooks/use-two-factor-auth";

import { SharedData } from "@/types";

export default function TwoFactor() {
  const { auth } = usePage<SharedData>().props;
  const { requiresConfirmation = false, twoFactorEnabled = false } = auth.user;

  const {
    qrCodeSvg,
    hasSetupData,
    manualSetupKey,
    clearSetupData,
    fetchSetupData,
    recoveryCodesList,
    fetchRecoveryCodes,
    errors,
  } = useTwoFactorAuth();

  const [showSetupModal, setShowSetupModal] = useState<boolean>(false);

  return (
    <div className="space-y-6">
      <HeadingSmall
        description="Manage your two-factor authentication settings"
        title="Two-Factor Authentication"
      />

      {twoFactorEnabled ? (
        <div className="flex flex-col items-start justify-start space-y-4">
          <Badge variant="default">Enabled</Badge>

          <p className="text-muted-foreground">
            With two-factor authentication enabled, you will be prompted for a secure, random pin
            during login, which you can retrieve from the TOTP-supported application on your phone.
          </p>

          <TwoFactorRecoveryCodes
            errors={errors}
            fetchRecoveryCodes={fetchRecoveryCodes}
            recoveryCodesList={recoveryCodesList}
          />

          <div className="relative inline">
            <Form {...disable.form()}>
              {({ processing }) => (
                <Button type="submit" disabled={processing} variant="destructive">
                  <ShieldBan /> Disable 2FA
                </Button>
              )}
            </Form>
          </div>
        </div>
      ) : (
        <div className="flex flex-col items-start justify-start space-y-4">
          <Badge variant="destructive">Disabled</Badge>

          <p className="text-muted-foreground">
            When you enable two-factor authentication, you will be prompted for a secure pin during
            login. This pin can be retrieved from a TOTP-supported application on your phone.
          </p>

          <div>
            {hasSetupData ? (
              <Button onClick={() => setShowSetupModal(true)}>
                <ShieldCheck />
                Continue Setup
              </Button>
            ) : (
              <Form {...enable.form()} onSuccess={() => setShowSetupModal(true)}>
                {({ processing }) => (
                  <Button type="submit" disabled={processing}>
                    <ShieldCheck />
                    Enable 2FA
                  </Button>
                )}
              </Form>
            )}
          </div>
        </div>
      )}

      <TwoFactorSetupModal
        onClose={() => setShowSetupModal(false)}
        clearSetupData={clearSetupData}
        errors={errors}
        fetchSetupData={fetchSetupData}
        isOpen={showSetupModal}
        manualSetupKey={manualSetupKey}
        qrCodeSvg={qrCodeSvg}
        requiresConfirmation={requiresConfirmation}
        twoFactorEnabled={twoFactorEnabled}
      />
    </div>
  );
}
