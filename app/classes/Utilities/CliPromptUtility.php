<?php

declare(strict_types=1);

namespace App\Utilities;

use Throwable;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * The four questions a bin/ wizard ever asks -- yes/no, pick one, type a value,
 * type a secret -- rendered by gum when gum is installed and by Symfony Console
 * when it is not.
 *
 * Same arrangement CliPickerUtility already makes for fzf, and for the same
 * reason: setup.sh installs gum on real instances, so most labs get the arrow-key
 * version, while a dev box, a container or a machine with no network still gets
 * asked the question rather than being told the tool is missing. Nothing here
 * may require gum.
 *
 * gum renders its interface on stderr and prints only the answer on stdout, so
 * stdin and stderr are handed straight to the terminal and stdout is the pipe
 * the answer comes back through. Without that, gum either cannot find a terminal
 * to draw on or its drawing is captured as part of the answer.
 *
 * Every method returns null when the operator dismissed the question -- Esc in
 * gum, Ctrl+C or end-of-input in Symfony -- which a caller must read as "stop",
 * never as "no" or "empty". confirm() is the exception: it says so in its own
 * return type, because a cancelled confirmation is a refusal.
 */
final class CliPromptUtility
{
    private ?bool $gumAvailable = null;

    public function __construct(private readonly SymfonyStyle $io)
    {
    }

    /**
     * Is there a person on the other end? Both halves matter: a cron run has no
     * terminal, and INTELIS_NONINTERACTIVE is how setup.sh says "there is a
     * terminal, but nobody is watching it".
     */
    public static function isInteractive(): bool
    {
        if (PHP_SAPI !== 'cli' || getenv('INTELIS_NONINTERACTIVE') === '1') {
            return false;
        }

        return function_exists('stream_isatty') && @stream_isatty(STDIN);
    }

    public function usingGum(): bool
    {
        if ($this->gumAvailable === null) {
            $this->gumAvailable = self::isInteractive()
                && PHP_OS_FAMILY !== 'Windows'
                && CliPickerUtility::hasCommand('gum');
        }

        return $this->gumAvailable;
    }

    public function confirm(string $question, bool $default = true): bool
    {
        if ($this->usingGum()) {
            [, $exitCode] = $this->gum([
                'confirm',
                $question,
                '--affirmative=Yes',
                '--negative=No',
                $default ? '--default=true' : '--default=false',
            ]);

            // 0 affirmative, 1 negative, anything else dismissed -- and a
            // dismissed confirmation is a refusal, not a repeat of the question.
            return $exitCode === 0;
        }

        try {
            return $this->io->confirm($question, $default);
        } catch (Throwable) {
            return false;
        }
    }

    /**
     * @param list<string> $options
     */
    public function choose(string $header, array $options, ?string $default = null): ?string
    {
        $options = array_values($options);
        if ($options === []) {
            return null;
        }

        if ($this->usingGum()) {
            $args = ['choose', '--header=' . $header, '--height=' . (count($options) + 2)];
            if ($default !== null) {
                $args[] = '--selected=' . $default;
            }
            [$answer, $exitCode] = $this->gum([...$args, ...$options]);

            if ($exitCode !== 0) {
                return null;
            }

            // Only an answer gum was actually offered. A blank line, or anything
            // else that came back off the pipe, is not a choice.
            return in_array($answer, $options, true) ? $answer : null;
        }

        try {
            $chosen = $this->io->choice($header, $options, $default);
        } catch (Throwable) {
            return null;
        }

        return is_string($chosen) ? $chosen : null;
    }

    /**
     * @param (callable(string): ?string)|null $validate returns an error to show, or null when the value is good
     */
    public function text(
        string $header,
        string $default = '',
        ?callable $validate = null,
        string $placeholder = ''
    ): ?string {
        while (true) {
            $answer = $this->askOnce($header, $default, $placeholder);
            if ($answer === null) {
                return null;
            }

            $answer = trim($answer);
            if ($validate === null) {
                return $answer;
            }

            $problem = $validate($answer);
            if ($problem === null) {
                return $answer;
            }

            $this->io->warning($problem);
        }
    }

    public function password(string $header, bool $allowEmpty = false): ?string
    {
        while (true) {
            if ($this->usingGum()) {
                [$answer, $exitCode] = $this->gum(['input', '--password', '--header=' . $header]);
                if ($exitCode !== 0) {
                    return null;
                }
            } else {
                try {
                    $answer = $this->io->askHidden($header, static fn($value) => $value);
                } catch (Throwable) {
                    return null;
                }
                if ($answer === null) {
                    return null;
                }
            }

            if ($allowEmpty || $answer !== '') {
                return $answer;
            }

            $this->io->warning('That cannot be empty.');
        }
    }

    private function askOnce(string $header, string $default, string $placeholder): ?string
    {
        if ($this->usingGum()) {
            $args = ['input', '--header=' . $header];
            if ($default !== '') {
                $args[] = '--value=' . $default;
            }
            if ($placeholder !== '') {
                $args[] = '--placeholder=' . $placeholder;
            }
            [$answer, $exitCode] = $this->gum($args);

            return $exitCode === 0 ? $answer : null;
        }

        try {
            $answer = $this->io->ask($header, $default !== '' ? $default : null);
        } catch (Throwable) {
            return null;
        }

        return $answer === null ? '' : (string) $answer;
    }

    /**
     * @param list<string> $args
     * @return array{0: string, 1: int} the answer off stdout, and gum's exit code
     */
    private function gum(array $args): array
    {
        $command = 'gum';
        foreach ($args as $arg) {
            $command .= ' ' . escapeshellarg($arg);
        }

        // stdin and stderr are the terminal's own, so gum has somewhere to read
        // from and somewhere to draw. Only stdout is captured.
        $process = @proc_open(
            $command,
            [0 => STDIN, 1 => ['pipe', 'w'], 2 => STDERR],
            $pipes
        );

        if (!is_resource($process)) {
            // gum was on PATH a moment ago and now will not start. Stop claiming
            // it, so every later question falls through to Symfony rather than
            // failing the same way one at a time.
            $this->gumAvailable = false;
            return ['', 1];
        }

        $answer = stream_get_contents($pipes[1]);
        fclose($pipes[1]);
        $exitCode = proc_close($process);

        return [trim($answer === false ? '' : $answer), $exitCode];
    }
}
