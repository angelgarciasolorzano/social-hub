import type { JSX } from "react";

import { Link } from "@inertiajs/react";

import { FiInstagram } from "react-icons/fi";
import { IoLogoFacebook, IoLogoGithub, IoLogoLinkedin, IoLogoTwitch } from "react-icons/io5";

import type { VariantProps } from "class-variance-authority";
import { motion } from "motion/react";

import { AppLogoIcon } from "@/shared/components/logo";
import { GradientText, LightRays, LogoLoop, ShinyText } from "@/shared/components/reactBits";
import { Button } from "@/shared/components/shadcn/ui/button";

import type { ResolvedAppearance } from "@/shared/hooks";
import { useAppearance } from "@/shared/hooks";

import { cn } from "@/shared/lib";

function Landing() {
  const { resolvedAppearance } = useAppearance();

  return (
    <div
      className={cn(
        "relative isolate flex min-h-screen w-full flex-col",
        resolvedAppearance === "light" && "home-grid-background-light",
      )}
    >
      {resolvedAppearance === "dark" && (
        <LightRays
          className="absolute inset-0 z-0"
          distortion={0}
          fadeDistance={1}
          followMouse={true}
          lightSpread={0.5}
          mouseInfluence={0.1}
          noiseAmount={0}
          pulsating
          rayLength={3}
          raysColor="#f0f0f0"
          raysOrigin="top-center"
          raysSpeed={1}
          saturation={1}
        />
      )}

      <div className="relative z-10 flex min-h-screen flex-col">
        <LandingHeader />
        <LandingBody resolvedAppearance={resolvedAppearance} />
        <LandingFooter resolvedAppearance={resolvedAppearance} />
      </div>
    </div>
  );
}

interface LandingHeaderOptions {
  label: string;
  href: string;
  variant: VariantProps<typeof Button>["variant"];
}

function LandingHeader() {
  const headerOptions: LandingHeaderOptions[] = [
    { label: "Login", href: "/login", variant: "ghost" },
    { label: "Register", href: "/register", variant: "default" },
  ];

  return (
    <header
      className={cn(
        "mx-56 mt-6 flex items-center justify-between rounded-xl border px-12 py-4",
        "bg-gray-400/2",
        "dark:border-t-2 dark:bg-neutral-900/30 dark:backdrop-blur-lg",
      )}
    >
      <motion.div
        className="flex items-center gap-4 text-black dark:text-white"
        animate={{ x: 0, opacity: 1 }}
        initial={{ x: -60, opacity: 0 }}
        transition={{ duration: 0.7, ease: "easeInOut" }}
      >
        <AppLogoIcon className="size-7 fill-current" />

        <h1 className="text-lg font-bold">Social Hub</h1>
      </motion.div>

      <motion.nav
        className="flex items-center gap-4"
        animate={{ x: 0, opacity: 1 }}
        initial={{ x: 60, opacity: 0 }}
        transition={{ duration: 0.7, ease: "easeInOut" }}
      >
        {headerOptions.map((option) => (
          <Button className="cursor-pointer" asChild key={option.href} variant={option.variant}>
            <Link href={option.href} viewTransition>
              {option.label}
            </Link>
          </Button>
        ))}
      </motion.nav>
    </header>
  );
}

interface LandingBodyProps {
  resolvedAppearance: ResolvedAppearance;
}

interface LandingBodySocialInfo {
  node: JSX.Element;
  title: string;
  href?: string;
}

function LandingBody({ resolvedAppearance }: LandingBodyProps) {
  const isLightAppearance = resolvedAppearance === "light";

  const socialInfo: LandingBodySocialInfo[] = [
    { node: <IoLogoFacebook />, title: "Facebook", href: "https://www.facebook.com" },
    { node: <FiInstagram />, title: "Instagram", href: "https://www.instagram.com" },
    { node: <IoLogoGithub />, title: "GitHub", href: "https://github.com" },
    { node: <IoLogoLinkedin />, title: "LinkedIn", href: "https://www.linkedin.com" },
    { node: <IoLogoTwitch />, title: "Twitch", href: "https://www.twitch.tv" },
  ];

  return (
    <main className="flex flex-1 items-center justify-center">
      <motion.section
        className="flex max-w-2xl flex-col items-center gap-6 text-center"
        animate={{ opacity: 1, scale: 1 }}
        initial={{ opacity: 0, scale: 0.9 }}
        transition={{ duration: 0.7, ease: "easeInOut", delay: 0.3 }}
      >
        <span
          className={cn(
            isLightAppearance ? "home-announcement-badge-light" : "home-announcement-badge-dark",
          )}
        >
          <ShinyText
            color={isLightAppearance ? "#111827" : "#b5b5b5"}
            shineColor={isLightAppearance ? "#94a3b8" : "#ffffff"}
            text="¡Nuevo en 2026!"
          />
        </span>

        <h2 className="inline-flex gap-2 text-5xl font-extrabold dark:text-white">
          Bienvenido a
          <GradientText
            className="cursor-text"
            animationSpeed={1}
            colors={
              isLightAppearance
                ? ["#111827", "#374151", "#64748b", "#d1d5db"]
                : ["#fff", "#e0e0e0", "#bdbdbd", "#757575", "#fff"]
            }
            showBorder={false}
          >
            Social Hub
          </GradientText>
        </h2>

        <p className="text-3xl font-bold text-gray-800 dark:text-white/90">
          Social Hub es una plataforma de redes sociales que te conecta con tus amigos y familiares.
        </p>

        <p className="text-gray-700 dark:text-white/80">
          Comparte tus momentos favoritos, descubre contenido interesante y mantente al día con las
          últimas noticias. ¡Únete a la comunidad de Social Hub hoy mismo!
        </p>

        <div className="w-56">
          <LogoLoop
            className={resolvedAppearance === "light" ? "text-gray-800" : "text-white"}
            ariaLabel="Redes sociales populares"
            direction="left"
            gap={30}
            hoverSpeed={0}
            logoHeight={20}
            logos={socialInfo}
            scaleOnHover
            speed={10}
          />
        </div>
      </motion.section>
    </main>
  );
}

type LandingFooterProps = Pick<LandingBodyProps, "resolvedAppearance">;

function LandingFooter({ resolvedAppearance }: LandingFooterProps) {
  return (
    <motion.footer
      className={cn(
        "mx-auto my-4 text-sm text-gray-600 dark:text-gray-400",
        resolvedAppearance === "light" && "font-medium",
      )}
      animate={{ y: 0, opacity: 1 }}
      initial={{ y: 40, opacity: 0 }}
      transition={{ duration: 0.7, ease: "easeInOut", delay: 0.4 }}
    >
      &copy; 2026 - {new Date().getFullYear()} Social Hub. Todos los derechos reservados.
    </motion.footer>
  );
}

export default Landing;
