<?php

declare(strict_types=1);

use Carbon\Carbon;
use CyrildeWit\EloquentViewable\Data\ViewRecord;
use CyrildeWit\EloquentViewable\Recording\Streams\Contracts\StreamClient;
use CyrildeWit\EloquentViewable\Recording\Streams\StreamEntry;
use CyrildeWit\EloquentViewable\Recording\Streams\ViewStream;

function streamRecord(int $id): ViewRecord
{
    return new ViewRecord($id, 'posts', 'visitor_one', null, Carbon::parse('2021-01-01 12:30:00', 'UTC'));
}

function streamEntry(string $id): StreamEntry
{
    return new StreamEntry($id, ['viewable_id' => '1', 'viewable_type' => 'posts', 'viewed_at' => '2021-01-01T12:30:00+00:00']);
}

beforeEach(function (): void {
    $this->client = Mockery::mock(StreamClient::class);
    $this->stream = new ViewStream($this->client, 'views', 'flushers', 'worker', 30_000, 2);
});

it('appends one record as encoded fields', function (): void {
    $this->client->expects('add')->with('views', StreamEntry::encode(streamRecord(1)));

    $this->stream->append(streamRecord(1));
});

it('appends a batch in one call', function (): void {
    $this->client->expects('addMany')->with('views', [StreamEntry::encode(streamRecord(1)), StreamEntry::encode(streamRecord(2))]);

    $this->stream->appendMany((function (): Generator {
        yield streamRecord(1);
        yield streamRecord(2);
    })());
});

it('appends nothing for an empty batch', function (): void {
    $this->client->expects('addMany')->never();

    $this->stream->appendMany([]);
});

it('creates the group once before taking entries', function (): void {
    $this->client->expects('createGroup')->once()->with('views', 'flushers');
    $this->client->allows('claim')->andReturn([]);
    $this->client->allows('read')->andReturn([]);

    $this->stream->take(10);
    $this->stream->take(10);
});

it('takes abandoned entries first and fills up with new ones', function (): void {
    $this->client->allows('createGroup');
    $this->client->expects('claim')->with('views', 'flushers', 'worker', 30_000, 3)->andReturn([streamEntry('1-0')]);
    $this->client->expects('read')->with('views', 'flushers', 'worker', 2)->andReturn([streamEntry('2-0'), streamEntry('3-0')]);

    expect($this->stream->take(3))->toEqual([streamEntry('1-0'), streamEntry('2-0'), streamEntry('3-0')]);
});

it('reads nothing new when the abandoned entries fill the limit', function (): void {
    $this->client->allows('createGroup');
    $this->client->expects('claim')->andReturn([streamEntry('1-0'), streamEntry('2-0')]);
    $this->client->expects('read')->never();

    expect($this->stream->take(2))->toHaveCount(2);
});

it('acknowledges entries and drops them from the stream', function (): void {
    $this->client->expects('acknowledge')->with('views', 'flushers', ['1-0', '2-0'])->globally()->ordered();
    $this->client->expects('delete')->with('views', ['1-0', '2-0'])->globally()->ordered();

    $this->stream->acknowledge(['1-0', '2-0']);
});

it('acknowledges nothing for no ids', function (): void {
    $this->client->expects('acknowledge')->never();
    $this->client->expects('delete')->never();

    $this->stream->acknowledge([]);
});

it('reads every entry a page at a time', function (): void {
    $this->client->expects('range')->with('views', null, 2)->andReturn([streamEntry('1-0'), streamEntry('2-0')]);
    $this->client->expects('range')->with('views', '2-0', 2)->andReturn([streamEntry('3-0'), streamEntry('4-0')]);
    $this->client->expects('range')->with('views', '4-0', 2)->andReturn([streamEntry('5-0')]);

    expect(iterator_to_array($this->stream->entries()))->toEqual([
        streamEntry('1-0'), streamEntry('2-0'), streamEntry('3-0'), streamEntry('4-0'), streamEntry('5-0'),
    ]);
});

it('stops reading after an empty stream', function (): void {
    $this->client->expects('range')->once()->with('views', null, 2)->andReturn([]);

    expect(iterator_to_array($this->stream->entries()))->toBeEmpty();
});

it('deletes entries by id', function (): void {
    $this->client->expects('delete')->with('views', ['1-0']);

    $this->stream->delete(['1-0']);
});

it('deletes nothing for no ids', function (): void {
    $this->client->expects('delete')->never();

    $this->stream->delete([]);
});
