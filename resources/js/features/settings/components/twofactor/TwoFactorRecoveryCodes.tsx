import { useCallback, useEffect, useRef, useState } from "react";

import { Form } from "@inertiajs/react";

import { Eye, EyeOff, LockKeyhole, RefreshCw } from "lucide-react";

import { regenerateRecoveryCodes } from "@/routes/two-factor";

import AlertError from "@/components/AlertError";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card";

interface TwoFactorRecoveryCodesProps {
  errors: string[];
  fetchRecoveryCodes: () => Promise<void>;
  recoveryCodesList: string[];
}

export default function TwoFactorRecoveryCodes({
  errors,
  fetchRecoveryCodes,
  recoveryCodesList,
}: TwoFactorRecoveryCodesProps) {
  const [codesAreVisible, setCodesAreVisible] = useState<boolean>(false);
  const codesSectionRef = useRef<HTMLDivElement | null>(null);
  const canRegenerateCodes = recoveryCodesList.length > 0 && codesAreVisible;

  const toggleCodesVisibility = useCallback(async () => {
    if (!codesAreVisible && !recoveryCodesList.length) {
      await fetchRecoveryCodes();
    }

    setCodesAreVisible(!codesAreVisible);

    if (!codesAreVisible) {
      setTimeout(() => {
        codesSectionRef.current?.scrollIntoView({
          behavior: "smooth",
          block: "nearest",
        });
      });
    }
  }, [codesAreVisible, recoveryCodesList.length, fetchRecoveryCodes]);

  useEffect(() => {
    if (!recoveryCodesList.length) {
      fetchRecoveryCodes();
    }
  }, [recoveryCodesList.length, fetchRecoveryCodes]);

  const RecoveryCodeIconComponent = codesAreVisible ? EyeOff : Eye;

  return (
    <Card>
      <CardHeader>
        <CardTitle className="flex gap-3">
          <LockKeyhole aria-hidden="true" className="size-4" />
          2FA Códigos de recuperación
        </CardTitle>
        <CardDescription>
          Los códigos de recuperación te permiten recuperar el acceso si pierdes tu dispositivo con
          autenticación de dos factores. Guárdalos en un gestor de contraseñas seguro.
        </CardDescription>
      </CardHeader>
      <CardContent>
        <div className="flex flex-col gap-3 select-none sm:flex-row sm:items-center sm:justify-between">
          <Button
            aria-controls="recovery-codes-section"
            aria-expanded={codesAreVisible}
            className="w-fit cursor-pointer"
            onClick={toggleCodesVisibility}
          >
            <RecoveryCodeIconComponent aria-hidden="true" className="size-4" />
            {codesAreVisible ? "Ocultar" : "Ver"} Códigos de recuperación
          </Button>

          {canRegenerateCodes && (
            <Form
              {...regenerateRecoveryCodes.form()}
              onSuccess={fetchRecoveryCodes}
              options={{ preserveScroll: true }}
            >
              {({ processing }) => (
                <Button
                  type="submit"
                  aria-describedby="regenerate-warning"
                  className="cursor-pointer"
                  disabled={processing}
                  variant="secondary"
                >
                  <RefreshCw /> Regenerar códigos
                </Button>
              )}
            </Form>
          )}
        </div>
        <div
          id="recovery-codes-section"
          aria-hidden={!codesAreVisible}
          className={`relative overflow-hidden transition-all duration-300 ${codesAreVisible ? "h-auto opacity-100" : "h-0 opacity-0"}`}
        >
          <div className="mt-3 space-y-3">
            {errors?.length ? (
              <AlertError errors={errors} />
            ) : (
              <>
                <div
                  aria-label="Recovery codes"
                  className="grid gap-1 rounded-lg bg-muted p-4 font-mono text-sm"
                  ref={codesSectionRef}
                  role="list"
                >
                  {recoveryCodesList.length ? (
                    recoveryCodesList.map((code, index) => (
                      <div className="select-text" key={index} role="listitem">
                        {code}
                      </div>
                    ))
                  ) : (
                    <div aria-label="Loading recovery codes" className="space-y-2">
                      {Array.from({ length: 8 }, (_, index) => (
                        <div
                          aria-hidden="true"
                          className="h-4 animate-pulse rounded bg-muted-foreground/20"
                          key={index}
                        />
                      ))}
                    </div>
                  )}
                </div>

                <div className="text-xs text-muted-foreground select-none">
                  <p id="regenerate-warning">
                    Cada código de recuperación se puede usar una vez para acceder a tu cuenta y se
                    eliminará después de su uso. Si necesitas más, haz clic en{" "}
                    <span className="font-bold">Regenerar códigos</span> arriba.
                  </p>
                </div>
              </>
            )}
          </div>
        </div>
      </CardContent>
    </Card>
  );
}
