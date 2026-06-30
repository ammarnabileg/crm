<?php

declare(strict_types=1);

namespace HaHireAI\Modules\Recruitment\Domain;

/**
 * The matching brain shared by the Resume Structurer and the Analysis Engine:
 * a curated ontology of skills (with aliases/synonyms), spoken languages, degree
 * vocabulary, and seniority cues. It lets the NO-AI engine recognise that "JS" ==
 * "JavaScript", "k8s" == "Kubernetes", "postgres" == "PostgreSQL", and that a
 * "Lead" title outranks a "Junior" one — so matching is genuinely smart, not a
 * literal substring check. Pure, deterministic, fully unit-testable.
 */
final class SkillOntology
{
    /**
     * canonical skill => list of aliases (all matched case-insensitively, on
     * word boundaries). The canonical form is what we report.
     *
     * @var array<string, list<string>>
     */
    public const SKILLS = [
        'PHP' => ['php', 'php8', 'php 8'],
        'Laravel' => ['laravel'],
        'Symfony' => ['symfony'],
        'JavaScript' => ['javascript', 'js', 'ecmascript', 'es6', 'es2015'],
        'TypeScript' => ['typescript', 'ts'],
        'Node.js' => ['node', 'node.js', 'nodejs'],
        'React' => ['react', 'react.js', 'reactjs'],
        'Vue.js' => ['vue', 'vue.js', 'vuejs'],
        'Angular' => ['angular', 'angularjs'],
        'Svelte' => ['svelte'],
        'Next.js' => ['next.js', 'nextjs'],
        'Python' => ['python', 'py'],
        'Django' => ['django'],
        'Flask' => ['flask'],
        'FastAPI' => ['fastapi'],
        'Java' => ['java'],
        'Spring' => ['spring', 'spring boot', 'springboot'],
        'Kotlin' => ['kotlin'],
        'C#' => ['c#', 'csharp', 'c sharp'],
        '.NET' => ['.net', 'dotnet', 'asp.net', 'aspnet'],
        'C++' => ['c++', 'cpp'],
        'C' => ['c language'],
        'Go' => ['golang', 'go lang'],
        'Rust' => ['rust'],
        'Ruby' => ['ruby'],
        'Rails' => ['rails', 'ruby on rails', 'ror'],
        'Swift' => ['swift'],
        'Objective-C' => ['objective-c', 'objective c', 'objc'],
        'Scala' => ['scala'],
        'Elixir' => ['elixir'],
        'Dart' => ['dart'],
        'Flutter' => ['flutter'],
        'React Native' => ['react native'],
        'SQL' => ['sql'],
        'MySQL' => ['mysql', 'mariadb'],
        'PostgreSQL' => ['postgresql', 'postgres', 'psql'],
        'MongoDB' => ['mongodb', 'mongo'],
        'Redis' => ['redis'],
        'SQLite' => ['sqlite'],
        'Elasticsearch' => ['elasticsearch', 'elastic search', 'opensearch'],
        'Oracle' => ['oracle db', 'oracle database'],
        'SQL Server' => ['sql server', 'mssql', 't-sql'],
        'AWS' => ['aws', 'amazon web services', 'ec2', 's3', 'lambda'],
        'Azure' => ['azure', 'microsoft azure'],
        'GCP' => ['gcp', 'google cloud', 'google cloud platform'],
        'Docker' => ['docker'],
        'Kubernetes' => ['kubernetes', 'k8s'],
        'Terraform' => ['terraform'],
        'Ansible' => ['ansible'],
        'Jenkins' => ['jenkins'],
        'CI/CD' => ['ci/cd', 'cicd', 'continuous integration', 'continuous delivery'],
        'Linux' => ['linux', 'unix', 'ubuntu', 'debian', 'centos'],
        'Git' => ['git', 'github', 'gitlab', 'bitbucket'],
        'GraphQL' => ['graphql'],
        'REST' => ['rest', 'rest api', 'restful'],
        'gRPC' => ['grpc'],
        'HTML' => ['html', 'html5'],
        'CSS' => ['css', 'css3'],
        'Tailwind' => ['tailwind', 'tailwindcss'],
        'SASS' => ['sass', 'scss'],
        'Bootstrap' => ['bootstrap'],
        'Machine Learning' => ['machine learning', 'ml'],
        'Deep Learning' => ['deep learning', 'neural networks'],
        'TensorFlow' => ['tensorflow'],
        'PyTorch' => ['pytorch'],
        'NLP' => ['nlp', 'natural language processing'],
        'Pandas' => ['pandas'],
        'NumPy' => ['numpy'],
        'Data Analysis' => ['data analysis', 'data analytics'],
        'Power BI' => ['power bi', 'powerbi'],
        'Tableau' => ['tableau'],
        'Excel' => ['excel', 'microsoft excel'],
        'Kafka' => ['kafka'],
        'RabbitMQ' => ['rabbitmq'],
        'Microservices' => ['microservices', 'micro services'],
        'Agile' => ['agile'],
        'Scrum' => ['scrum'],
        'Kanban' => ['kanban'],
        'Jira' => ['jira'],
        'Figma' => ['figma'],
        'Adobe XD' => ['adobe xd', 'xd'],
        'Photoshop' => ['photoshop'],
        'Illustrator' => ['illustrator'],
        'UI/UX' => ['ui/ux', 'ux', 'ui design', 'user experience'],
        'SEO' => ['seo', 'search engine optimization'],
        'Project Management' => ['project management'],
        'Leadership' => ['leadership', 'team lead', 'people management'],
        'Communication' => ['communication'],
        'Sales' => ['sales'],
        'Marketing' => ['marketing', 'digital marketing'],
        'Accounting' => ['accounting'],
        'Recruiting' => ['recruiting', 'recruitment', 'talent acquisition'],
        'Customer Support' => ['customer support', 'customer service'],
    ];

    /** Spoken languages (canonical => aliases). @var array<string, list<string>> */
    public const LANGUAGES = [
        'Arabic' => ['arabic', 'عربي', 'العربية'],
        'English' => ['english', 'انجليزي', 'الإنجليزية'],
        'French' => ['french', 'français', 'francais'],
        'German' => ['german', 'deutsch'],
        'Spanish' => ['spanish', 'español', 'espanol'],
        'Italian' => ['italian', 'italiano'],
        'Turkish' => ['turkish', 'türkçe'],
        'Russian' => ['russian'],
        'Chinese' => ['chinese', 'mandarin'],
        'Hindi' => ['hindi'],
        'Portuguese' => ['portuguese', 'português'],
        'Urdu' => ['urdu'],
    ];

    /** Education level => detection keywords, ranked low→high. */
    public const EDUCATION_LEVELS = [
        'high_school' => ['high school', 'secondary school', 'thanaweya', 'diploma'],
        'associate' => ['associate degree', 'associate of'],
        'bachelor' => ['bachelor', 'bsc', 'b.sc', 'b.s.', 'ba ', 'b.a', 'be ', 'b.e', 'btech', 'b.tech', 'undergraduate', 'licence', 'bachelors'],
        'master' => ['master', 'msc', 'm.sc', 'm.s.', 'ma ', 'm.a', 'mba', 'mtech', 'm.tech', 'postgraduate', 'masters'],
        'doctorate' => ['phd', 'ph.d', 'doctorate', 'doctoral', 'dphil'],
    ];

    public const EDUCATION_RANK = [
        'high_school' => 1, 'associate' => 2, 'bachelor' => 3, 'master' => 4, 'doctorate' => 5,
    ];

    /** Certificate cue words. @var list<string> */
    public const CERTIFICATION_CUES = [
        'certified', 'certificate', 'certification', 'aws certified', 'pmp', 'cissp',
        'ccna', 'ccnp', 'scrum master', 'psm', 'pmi', 'itil', 'comptia', 'oracle certified',
        'microsoft certified', 'google certified', 'professional certificate', 'nanodegree',
    ];

    /** Seniority cue => weight (higher = more senior). @var array<string, int> */
    public const SENIORITY_CUES = [
        'intern' => 1, 'internship' => 1, 'trainee' => 1,
        'junior' => 2, 'jr' => 2, 'entry level' => 2, 'entry-level' => 2,
        'associate' => 3,
        'mid' => 4, 'intermediate' => 4,
        'senior' => 5, 'sr' => 5,
        'lead' => 6, 'team lead' => 6, 'tech lead' => 6, 'principal' => 6, 'staff' => 6,
        'manager' => 7, 'head of' => 7, 'director' => 8,
        'vp' => 9, 'vice president' => 9, 'chief' => 10, 'cto' => 10, 'ceo' => 10, 'executive' => 9,
    ];

    /** Map the job's `seniority` enum to a comparable rank (1..10). */
    public const SENIORITY_LEVELS = [
        'intern' => 1, 'junior' => 2, 'associate' => 3, 'mid' => 4, 'mid_level' => 4,
        'senior' => 5, 'lead' => 6, 'principal' => 6, 'staff' => 6, 'manager' => 7,
        'director' => 8, 'executive' => 9,
    ];

    /**
     * Recognise canonical skills present in a blob of text (word-boundary,
     * case-insensitive, alias-aware).
     *
     * @return list<string> canonical skill names, in ontology order
     */
    public static function detectSkills(string $text): array
    {
        $hay = ' ' . mb_strtolower($text) . ' ';
        $found = [];
        foreach (self::SKILLS as $canonical => $aliases) {
            foreach ($aliases as $alias) {
                if (self::aliasPresent($hay, $alias)) {
                    $found[$canonical] = true;
                    break;
                }
            }
        }

        return array_keys($found);
    }

    /**
     * Normalise an arbitrary skill term (from a job's required_skills/keywords)
     * to its canonical ontology name when known; otherwise return it trimmed.
     */
    public static function canonical(string $term): string
    {
        $t = mb_strtolower(trim($term));
        if ($t === '') {
            return '';
        }
        foreach (self::SKILLS as $canonical => $aliases) {
            if ($t === mb_strtolower($canonical)) {
                return $canonical;
            }
            foreach ($aliases as $alias) {
                if ($t === $alias) {
                    return $canonical;
                }
            }
        }

        return trim($term);
    }

    /** Is a canonical/aliased skill present in text? */
    public static function skillPresent(string $skill, string $text): bool
    {
        $hay = ' ' . mb_strtolower($text) . ' ';
        $canonical = self::canonical($skill);
        $aliases = self::SKILLS[$canonical] ?? [mb_strtolower($skill)];
        $aliases[] = mb_strtolower($canonical);
        foreach (array_unique($aliases) as $alias) {
            if (self::aliasPresent($hay, $alias)) {
                return true;
            }
        }

        return false;
    }

    /** @return list<string> spoken languages detected in text */
    public static function detectLanguages(string $text): array
    {
        $hay = ' ' . mb_strtolower($text) . ' ';
        $found = [];
        foreach (self::LANGUAGES as $canonical => $aliases) {
            foreach ($aliases as $alias) {
                if (self::aliasPresent($hay, $alias)) {
                    $found[$canonical] = true;
                    break;
                }
            }
        }

        return array_keys($found);
    }

    /** Highest education level detected (key) or null. */
    public static function detectEducationLevel(string $text): ?string
    {
        $hay = mb_strtolower($text);
        $best = null;
        $bestRank = 0;
        foreach (self::EDUCATION_LEVELS as $level => $cues) {
            foreach ($cues as $cue) {
                if (str_contains($hay, $cue) && self::EDUCATION_RANK[$level] > $bestRank) {
                    $best = $level;
                    $bestRank = self::EDUCATION_RANK[$level];
                }
            }
        }

        return $best;
    }

    /** Infer a seniority rank (1..10) from a title/text using the strongest cue. */
    public static function inferSeniority(string $text): int
    {
        $hay = ' ' . mb_strtolower($text) . ' ';
        $rank = 0;
        foreach (self::SENIORITY_CUES as $cue => $weight) {
            if (self::aliasPresent($hay, $cue) && $weight > $rank) {
                $rank = $weight;
            }
        }

        return $rank;
    }

    /** Map a job seniority enum to a rank (0 when unknown). */
    public static function jobSeniorityRank(?string $seniority): int
    {
        if ($seniority === null) {
            return 0;
        }

        return self::SENIORITY_LEVELS[mb_strtolower(trim($seniority))] ?? 0;
    }

    private static function aliasPresent(string $paddedLowerHay, string $alias): bool
    {
        $alias = trim($alias);
        if ($alias === '') {
            return false;
        }
        // Word-boundary match that tolerates the symbol-heavy skill names
        // (c++, c#, .net, node.js) which \b would mishandle.
        $quoted = preg_quote($alias, '/');
        $pattern = '/(?<![a-z0-9+#.])' . $quoted . '(?![a-z0-9+#])/iu';

        return preg_match($pattern, $paddedLowerHay) === 1;
    }
}
