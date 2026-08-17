import type { JSX } from "react";

import { Head } from "@inertiajs/react";

function TrustedDevice(): JSX.Element {
  return (
    <>
      <Head title="Two Factor Authentication" />
      <div>Trusted Device</div>
    </>
  );
}

export default TrustedDevice;
