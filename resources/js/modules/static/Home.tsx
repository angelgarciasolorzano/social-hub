import type { JSX } from "react";

import { Link } from "@inertiajs/react";

import { FiInstagram } from "react-icons/fi";
import { IoLogoFacebook, IoLogoGithub, IoLogoLinkedin, IoLogoTwitch } from "react-icons/io5";

import type { VariantProps } from "class-variance-authority";

import { AppLogoIcon } from "@/shared/components/logo";
import { GradientText, LightRays, LogoLoop, ShinyText } from "@/shared/components/reactBits";
import { Button } from "@/shared/components/ui/button";

import type { ResolvedAppearance } from "@/shared/hooks";
import { useAppearance } from "@/shared/hooks";

import { cn } from "@/shared/lib";

function Home() {
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
        <HomeHeader />
        <HomeBody resolvedAppearance={resolvedAppearance} />
        <HomeFooter resolvedAppearance={resolvedAppearance} />
      </div>
    </div>
  );
}

interface HomeHeaderOptions {
  label: string;
  href: string;
  variant: VariantProps<typeof Button>["variant"];
}

function HomeHeader() {
  const headerOptions: HomeHeaderOptions[] = [
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
      <div className="flex items-center gap-4 text-black dark:text-white">
        <AppLogoIcon className="size-7 fill-current" />

        <h1 className="text-lg font-bold">Social Hub</h1>
      </div>

      <nav className="flex items-center gap-4">
        {headerOptions.map((option) => (
          <Button className="cursor-pointer" asChild key={option.href} variant={option.variant}>
            <Link href={option.href}>{option.label}</Link>
          </Button>
        ))}
      </nav>
    </header>
  );
}

interface HomeBodyProps {
  resolvedAppearance: ResolvedAppearance;
}

interface HomeBodySocialInfo {
  node: JSX.Element;
  title: string;
  href?: string;
}

function HomeBody({ resolvedAppearance }: HomeBodyProps) {
  const isLightAppearance = resolvedAppearance === "light";

  const socialInfo: HomeBodySocialInfo[] = [
    { node: <IoLogoFacebook />, title: "Facebook", href: "https://www.facebook.com" },
    { node: <FiInstagram />, title: "Instagram", href: "https://www.instagram.com" },
    { node: <IoLogoGithub />, title: "GitHub", href: "https://github.com" },
    { node: <IoLogoLinkedin />, title: "LinkedIn", href: "https://www.linkedin.com" },
    { node: <IoLogoTwitch />, title: "Twitch", href: "https://www.twitch.tv" },
  ];

  return (
    <main className="flex flex-1 items-center justify-center">
      <section className="flex max-w-2xl flex-col items-center gap-6 text-center">
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
      </section>
    </main>
  );
}

type HomeFooterProps = Pick<HomeBodyProps, "resolvedAppearance">;

function HomeFooter({ resolvedAppearance }: HomeFooterProps) {
  return (
    <footer
      className={cn(
        "mx-auto my-4 text-sm text-gray-600 dark:text-gray-400",
        resolvedAppearance === "light" && "font-medium",
      )}
    >
      &copy; 2026 - {new Date().getFullYear()} Social Hub. Todos los derechos reservados.
    </footer>
  );
}

export default Home;
