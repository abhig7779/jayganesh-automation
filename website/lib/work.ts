// Jobs shown on Our Work and the home page.
// PLACEHOLDERS: replace each entry with a real job (car, what was done, photos)
// before launch. Never publish a job that didn't happen.
export type Job = {
  slug: string;
  car: string;
  job: string;
  service: string; // link target
  time: string;
  placeholder?: boolean;
  photo?: import("./media").MediaKey;
};

export const jobs: Job[] = [
  { slug: "suv-full-body-ppf", photo: "ppfApply", car: "SUV", job: "Full-body PPF", service: "/ppf-pune/", time: "4 days", placeholder: true },
  { slug: "sedan-ceramic", photo: "beadingDark", car: "Sedan", job: "Paint correction and ceramic", service: "/ceramic-coating-pune/", time: "2 days", placeholder: true },
  { slug: "muv-seat-covers", photo: "workMuvSeats", car: "MUV", job: "Custom seat covers", service: "/seat-covers/", time: "Same day", placeholder: true },
  { slug: "hatchback-detailing", photo: "workHatchInterior", car: "Hatchback", job: "Interior deep clean", service: "/car-detailing-pune/", time: "Half a day", placeholder: true },
  { slug: "new-suv-protection", photo: "newCar", car: "New SUV", job: "Front PPF and ceramic", service: "/new-car-protection/", time: "2 days", placeholder: true },
  { slug: "suv-stereo-camera", photo: "workSuvStereo", car: "SUV", job: "Android stereo and 360° camera", service: "/accessories/", time: "Same day", placeholder: true },
];
