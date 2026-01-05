import { SettingLabelSidebar } from "./settingsSidebarItems";

interface SettingsViewItems {
  label: string;
  description: string;
}

export const settingsViewItems: Record<SettingLabelSidebar, SettingsViewItems> = {
  [SettingLabelSidebar.Perfil]: {
    label: "Perfil",
    description: "Configura tu información personal y preferencias de cuenta.",
  },
  [SettingLabelSidebar.Contraseña]: {
    label: "Contraseña",
    description: "Cambia tu contraseña para mantener tu cuenta segura.",
  },
  [SettingLabelSidebar.TwoFactor]: {
    label: "Two Factor Authentication",
    description: "Añade una capa extra de seguridad a tu cuenta.",
  },
  [SettingLabelSidebar.Apariencia]: {
    label: "Apariencia",
    description: "Personaliza el tema y la apariencia de la aplicación.",
  },
};
