import type { ChangeEvent, JSX } from "react";
import { useState } from "react";

import { Form } from "@inertiajs/react";

import { FileText, Globe, Languages, Mail, Phone, UserRound } from "lucide-react";

import type { AccountSettings } from "@/modules/setting/modules/accountSettings/types/accountSettings";

import { update } from "@/shared/wayfinder/actions/App/User/Modules/AccountSettings/Controllers/AccountSettingsController";

import { InputError, LabelForm } from "@/shared/components/form";
import { Button } from "@/shared/components/shadcn/ui/button";
import {
  Card,
  CardContent,
  CardDescription,
  CardFooter,
  CardHeader,
} from "@/shared/components/shadcn/ui/card";
import { Input } from "@/shared/components/shadcn/ui/input";
import {
  Select,
  SelectContent,
  SelectGroup,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from "@/shared/components/shadcn/ui/select";
import { Textarea } from "@/shared/components/shadcn/ui/textarea";

import { useTranslation } from "@/shared/hooks";

const BIOGRAPHY_MAX_LENGTH = 160;

interface AccountSettingsProfileFormProps {
  accountSettings: AccountSettings;
}

function AccountSettingsProfileForm({
  accountSettings,
}: AccountSettingsProfileFormProps): JSX.Element {
  const { t } = useTranslation();
  const [biographyLength, setBiographyLength] = useState<number>(
    accountSettings.biography?.length ?? 0,
  );

  function handleBiographyChange(event: ChangeEvent<HTMLTextAreaElement>): void {
    setBiographyLength(event.currentTarget.value.length);
  }

  return (
    <Form
      {...update.form()}
      className="w-full"
      options={{
        preserveScroll: true,
      }}
      setDefaultsOnSuccess
    >
      {({ errors, processing, recentlySuccessful }) => (
        <Card>
          <CardHeader>
            <h2 className="text-lg font-semibold tracking-tight" id="account-profile-heading">
              {t("setting/account-settings.profile_form.title")}
            </h2>
            <CardDescription>
              {t("setting/account-settings.profile_form.description")}
            </CardDescription>
          </CardHeader>

          <CardContent className="grid min-w-0 grid-cols-1 items-start gap-x-6 gap-y-6 px-4 sm:px-6 md:grid-cols-2">
            <div className="grid min-w-0 gap-2">
              <LabelForm error={errors["name"]} htmlFor="account-name">
                <UserRound
                  aria-hidden="true"
                  className="shrink-0 text-muted-foreground"
                  size={16}
                />
                {t("setting/account-settings.profile_form.name")}
              </LabelForm>

              <Input
                id="account-name"
                name="name"
                type="text"
                autoComplete="name"
                aria-describedby={errors["name"] !== undefined ? "account-name-error" : undefined}
                aria-invalid={errors["name"] !== undefined}
                defaultValue={accountSettings.name}
                maxLength={255}
                required
              />

              <InputError id="account-name-error" message={errors["name"]} role="alert" />
            </div>

            <div className="grid min-w-0 gap-2">
              <LabelForm error={errors["phone"]} htmlFor="account-phone">
                <Phone aria-hidden="true" className="shrink-0 text-muted-foreground" size={16} />
                {t("setting/account-settings.profile_form.phone")}
              </LabelForm>

              <Input
                id="account-phone"
                name="phone"
                type="tel"
                autoComplete="tel"
                aria-describedby={errors["phone"] !== undefined ? "account-phone-error" : undefined}
                aria-invalid={errors["phone"] !== undefined}
                defaultValue={accountSettings.phone ?? ""}
                placeholder={
                  accountSettings.phone ??
                  t("setting/account-settings.profile_form.phone_placeholder")
                }
                maxLength={255}
              />
              <InputError id="account-phone-error" message={errors["phone"]} role="alert" />
            </div>

            <div className="grid min-w-0 gap-2">
              <LabelForm htmlFor="account-email">
                <Mail aria-hidden="true" className="shrink-0 text-muted-foreground" size={16} />
                {t("setting/account-settings.profile_form.email")}
              </LabelForm>

              <Input
                id="account-email"
                type="email"
                autoComplete="email"
                aria-describedby="account-email-help"
                value={accountSettings.email}
                readOnly
              />

              <p className="text-sm text-muted-foreground" id="account-email-help">
                {t("setting/account-settings.profile_form.email_help")}
              </p>
            </div>

            <div className="grid min-w-0 gap-2">
              <LabelForm error={errors["preferredLocale"]} htmlFor="account-locale">
                <Globe aria-hidden="true" className="shrink-0 text-muted-foreground" size={16} />
                {t("setting/account-settings.profile_form.locale")}
              </LabelForm>

              <Select
                defaultValue={accountSettings.preferredLocale}
                name="preferredLocale"
                required
              >
                <SelectTrigger
                  aria-describedby={
                    errors["preferredLocale"] !== undefined
                      ? "account-locale-help account-locale-error"
                      : "account-locale-help"
                  }
                  aria-invalid={errors["preferredLocale"] !== undefined}
                  className="h-12 w-full"
                  id="account-locale"
                >
                  <Languages aria-hidden="true" />

                  <SelectValue />
                </SelectTrigger>
                <SelectContent>
                  <SelectGroup>
                    <SelectItem value="en">{t("setting/account-settings.locales.en")}</SelectItem>
                    <SelectItem value="es">{t("setting/account-settings.locales.es")}</SelectItem>
                  </SelectGroup>
                </SelectContent>
              </Select>

              {errors["preferredLocale"] !== undefined && (
                <InputError
                  id="account-locale-error"
                  message={errors["preferredLocale"]}
                  role="alert"
                />
              )}

              <p className="text-sm text-muted-foreground" id="account-locale-help">
                {t("setting/account-settings.profile_form.locale_help")}
              </p>
            </div>

            <div className="grid min-w-0 gap-2 md:col-span-2">
              <LabelForm error={errors["biography"]} htmlFor="account-biography">
                <FileText aria-hidden="true" className="shrink-0 text-muted-foreground" size={16} />
                {t("setting/account-settings.profile_form.biography")}
              </LabelForm>

              <Textarea
                id="account-biography"
                className="min-h-32 resize-none"
                name="biography"
                aria-describedby={`account-biography-help account-biography-count${errors["biography"] !== undefined ? " account-biography-error" : ""}`}
                aria-invalid={errors["biography"] !== undefined}
                defaultValue={accountSettings.biography ?? ""}
                maxLength={BIOGRAPHY_MAX_LENGTH}
                onChange={handleBiographyChange}
                rows={4}
              />

              <div className="flex flex-wrap justify-between gap-x-4 gap-y-1 text-sm text-muted-foreground">
                <p id="account-biography-help">
                  {t("setting/account-settings.profile_form.biography_help", {
                    max: BIOGRAPHY_MAX_LENGTH,
                  })}
                </p>
                <p id="account-biography-count">
                  {t("setting/account-settings.profile_form.biography_count", {
                    count: biographyLength,
                    max: BIOGRAPHY_MAX_LENGTH,
                  })}
                </p>
              </div>

              <InputError id="account-biography-error" message={errors["biography"]} role="alert" />
            </div>
          </CardContent>

          <CardFooter className="flex flex-wrap items-center gap-4 px-4 sm:px-6">
            <Button type="submit" disabled={processing}>
              {t("setting/account-settings.profile_form.save")}
            </Button>

            <p
              aria-atomic="true"
              aria-live="polite"
              className="text-sm text-muted-foreground"
              role="status"
            >
              {processing
                ? t("setting/account-settings.profile_form.saving")
                : recentlySuccessful
                  ? t("setting/account-settings.profile_form.saved")
                  : ""}
            </p>
          </CardFooter>
        </Card>
      )}
    </Form>
  );
}

export default AccountSettingsProfileForm;
