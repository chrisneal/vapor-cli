<?php

namespace Laravel\VaporCli;

use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Symfony\Component\Process\Process;

class Docker
{
    /**
     * Build a docker image.
     *
     * @param  string  $path
     * @param  string  $project
     * @param  string  $environment
     * @param  array  $cliBuildOptions
     * @param  array  $cliBuildArgs
     * @return void
     */
    public static function build($path, $project, $environment, $cliBuildOptions, $cliBuildArgs)
    {
        Process::fromShellCommandline(
            static::buildCommand(
                $project,
                $environment,
                $cliBuildOptions,
                Manifest::dockerBuildOptions($environment),
                $cliBuildArgs,
                Manifest::dockerBuildArgs($environment)
            ),
            $path
        )->setTimeout(null)->mustRun(function ($type, $line) {
            Helpers::write($line);
        });
    }

    /**
     * Create the Docker build command string.
     *
     * @param  string  $project
     * @param  string  $environment
     * @param  array  $cliBuildOptions
     * @param  array  $manifestBuildOptions
     * @param  array  $cliBuildArgs
     * @param  array  $manifestBuildArgs
     * @return string
     */
    public static function buildCommand($project, $environment, $cliBuildOptions, $manifestBuildOptions, $cliBuildArgs, $manifestBuildArgs)
    {
        $command = sprintf(
            'docker build --pull --file=%s --tag=%s ', 
            Manifest::dockerfile($environment),
            Str::slug($project).':'.$environment
        );

        $buildArgs = Collection::make($manifestBuildArgs)
            ->merge(Collection::make($cliBuildArgs)
                ->mapWithKeys(function ($value) {
                    [$key, $value] = explode('=', $value, 2);

                    return [$key => $value];
                })
            )->map(function ($value, $key) {
                return '--build-arg='.escapeshellarg("{$key}={$value}");
            })->implode(' ');

        $buildOptions = Collection::make($manifestBuildOptions)
            ->mapWithKeys(function ($value) {
                if (is_array($value)) {
                    return $value;
                }

                return [$value => null];
            })
            ->merge(Collection::make($cliBuildOptions)
                ->mapWithKeys(function ($value) {
                    if (! str_contains($value, '=')) {
                        return [$value => null];
                    }

                    [$key, $value] = explode('=', $value, 2);

                    return [$key => $value];
                })
            )->map(function ($value, $key) {
                if ($value === null) {
                    return "--{$key}";
                }

                return "--{$key}=".escapeshellarg($value);
            })->implode(' ');

        $command = $buildArgs ? $command.$buildArgs.' ' : $command;
        $command = $buildOptions ? $command.$buildOptions.' ' : $command;
        $command .= '.';

        return $command;
    }

    /**
     * Publish a docker image.
     *
     * @param  string  $path
     * @param  string  $project
     * @param  string  $environment
     * @param  string  $token
     * @param  string  $repoUri
     * @param  string  $tag
     * @return void
     */
    public static function publish($path, $project, $environment, $token, $repoUri, $tag)
    {
        Process::fromShellCommandline(
            sprintf('docker tag %s %s',
                Str::slug($project).':'.$environment,
                $repoUri.':'.$tag
            ),
            $path
        )->setTimeout(null)->mustRun();

        Process::fromShellCommandline(
            sprintf('docker login --username AWS --password %s %s',
                str_replace('AWS:', '', base64_decode($token)),
                explode('/', $repoUri)[0]
            ),
            $path
        )->setTimeout(null)->mustRun();

        Process::fromShellCommandline(
            sprintf('docker push %s',
                $repoUri.':'.$tag
            ),
            $path
        )->setTimeout(null)->mustRun(function ($type, $line) {
            Helpers::write($line);
        });
    }
}
