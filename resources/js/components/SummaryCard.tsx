import { Badge } from '@/components/ui/badge';
import ReactMarkdown from 'react-markdown';

interface Props {
    label: string;
    title: string;
    body: string;
    confidence?: number | null;
    children?: React.ReactNode;
}

const PROSE = 'prose prose-sm max-w-none dark:prose-invert prose-headings:font-semibold prose-h2:text-base prose-h2:mt-5 prose-h2:mb-1 prose-p:my-2';

export default function SummaryCard({ label, title, body, confidence, children }: Props): JSX.Element {
    const isLowConfidence = confidence !== null && confidence !== undefined && confidence < 60;

    return (
        <div className={`rounded-lg border p-4 ${isLowConfidence ? 'border-yellow-300' : 'border-border'}`}>
            <div className="mb-2 flex items-center justify-between">
                <span className="text-xs font-semibold uppercase tracking-wide text-muted-foreground">{label}</span>
                {confidence !== null && confidence !== undefined && (
                    <Badge
                        variant="outline"
                        className={isLowConfidence ? 'border-yellow-400 text-yellow-700' : ''}
                    >
                        {confidence}% betrouwbaar
                    </Badge>
                )}
            </div>

            <h3 className="mb-2 font-medium">{title}</h3>

            <div className={PROSE}>
                <ReactMarkdown>{body}</ReactMarkdown>
            </div>

            {children && <div className="mt-3">{children}</div>}
        </div>
    );
}
