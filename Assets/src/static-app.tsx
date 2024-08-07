import React from "react";
import { Payload } from "./main";
import { App } from "./parts/App";
const payload = (window as any).editor as Payload;

export const StaticEditor = () => {
    return (
        <App payload={payload} />
    )
}