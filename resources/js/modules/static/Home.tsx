import type { JSX } from "react";

import { Link } from "@inertiajs/react";

import { FiInstagram } from "react-icons/fi";
import { IoLogoFacebook, IoLogoGithub, IoLogoLinkedin, IoLogoTwitch } from "react-icons/io5";

import type { VariantProps } from "class-variance-authority";

import { AppLogoIcon } from "@/shared/components/logo";
import { GradientText, LightRays, LogoLoop, ShinyText } from "@/shared/components/reactBits";
import { Button } from "@/shared/components/ui/button";

import { cn } from "@/shared/lib";

function Home() {
  return (
    <div className="relative isolate flex min-h-screen w-full flex-col">
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

      <div className="relative z-10 flex min-h-screen flex-col">
        <HomeHeader />
        <HomeBody />
        <HomeFooter />
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
        "mx-56 mt-6 flex items-center justify-between rounded-xl px-12 py-4",
        "border border-t-2 bg-neutral-900/30 backdrop-blur-lg",
      )}
    >
      <div className="flex items-center gap-4">
        <AppLogoIcon className="size-7 fill-current text-black dark:text-white" />

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

interface HomeBodySocialInfo {
  node: JSX.Element;
  title: string;
  href?: string;
}

function HomeBody() {
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
        <span className="shiny-text-wrapper">
          <ShinyText text="¡Nuevo en 2026!" />
        </span>

        <h2 className="inline-flex gap-2 text-5xl font-extrabold text-white">
          Bienvenido a
          <GradientText
            animationSpeed={1}
            colors={["#fff", "#e0e0e0", "#bdbdbd", "#757575", "#fff"]}
            showBorder={false}
          >
            Social Hub
          </GradientText>
        </h2>

        <p className="text-3xl font-bold text-white/90">
          Social Hub es una plataforma de redes sociales que te conecta con tus amigos y familiares.
        </p>

        <p className="text-white/80">
          Comparte tus momentos favoritos, descubre contenido interesante y mantente al día con las
          últimas noticias. ¡Únete a la comunidad de Social Hub hoy mismo!
        </p>

        <div className="w-56">
          <LogoLoop
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

function HomeFooter() {
  return (
    <footer className="mx-auto my-4 text-sm">
      &copy; 2026 - {new Date().getFullYear()} Social Hub. Todos los derechos reservados.
    </footer>
  );
}

export default Home;
