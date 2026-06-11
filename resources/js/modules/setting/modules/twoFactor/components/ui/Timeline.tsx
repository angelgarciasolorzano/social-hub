export interface Step {
  number: number;
  title: string;
  description: string;
}

interface TimelineProps {
  steps: Step[];
}

function Timeline({ steps }: TimelineProps) {
  return (
    <div className="space-y-0">
      {steps.map((step, index) => (
        <TimelineStep
          key={step.number}
          number={step.number}
          title={step.title}
          description={step.description}
          isLast={index === steps.length - 1}
        />
      ))}
    </div>
  );
}

interface TimelineStepProps extends Step {
  isLast: boolean;
}

function TimelineStep({ number, title, description, isLast }: TimelineStepProps) {
  return (
    <div className="flex gap-4">
      <div className="flex flex-col items-center">
        <div className="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-green-50 font-semibold text-green-600 dark:bg-green-900/20 dark:text-green-500">
          {number}
        </div>

        {!isLast && <div className="w-[1.5px] flex-1 bg-border" />}
      </div>

      <div className="pt-1 pb-8">
        <h3 className="font-semibold">{title}</h3>

        <p className="mt-1 text-sm text-muted-foreground">{description}</p>
      </div>
    </div>
  );
}

export default Timeline;
